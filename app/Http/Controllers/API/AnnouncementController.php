<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AnnouncementTarget;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function index(Request $request)
{
    try {
        $q = Announcement::query()
            ->with(['author:id,name', 'targets', 'reads'])
            ->when($request->filled('visibility'), fn ($qq) =>
                $qq->where('visibility', $request->string('visibility'))
            )
            ->when($request->filled('author_id'), fn ($qq) =>
                $qq->where('author_user_id', $request->integer('author_id'))
            )
            ->when($request->filled('published'), fn ($qq) =>
                $qq->when(
                    $request->boolean('published'),
                    fn ($qqq) => $qqq->whereNotNull('published_at'),
                    fn ($qqq) => $qqq->whereNull('published_at')
                )
            );

        // término de búsqueda (tu frontend ya manda ?q=...)
        $term = trim((string) $request->query('q', ''));

        if ($term !== '') {
            // 1) Construye consulta boolean: +reunión* +otra*
            $tokens = preg_split('/\s+/u', $term) ?: [];
            $boolean = collect($tokens)
                ->filter()
                ->map(fn($w) => '+' . trim($w, "+-@><()~*\"'") . '*')
                ->implode(' ');

            // 2) Fallback LIKE (escapando % y _)
            $like = '%' . str_replace(['%','_'], ['\%','\_'], $term) . '%';

            $q->where(function ($sub) use ($boolean, $like) {
                // FULLTEXT en modo booleano (evita la regla del 50%)
                $sub->whereRaw("MATCH (title, body_md) AGAINST (? IN BOOLEAN MODE)", [$boolean])
                    // Fallback por si algo falla o para colaciones particulares
                    ->orWhere('title', 'like', $like)
                    ->orWhere('body_md', 'like', $like);
            });
        }

        return $q->orderByDesc('published_at')
                 ->orderByDesc('id')
                 ->paginate($request->integer('per_page', 15))
                 ->withQueryString();
    } catch (\Exception $e) {
        \Log::error('Announcements index error: ' . $e->getMessage());
        return response()->json([
            'response_code' => 500,
            'status' => 'error',
            'message' => 'Error interno del servidor: ' . $e->getMessage(),
        ], 500);
    }
}


    public function store(Request $request)
    {
        try{    
            $data = $request->validate([
                'title'      => ['bail','required','string','max:180'],
                'body_md'    => ['bail','required','string'],
                'visibility' => ['bail','required', Rule::in(['all','groups','users'])],
                'post'       => ['required','boolean'],

                // Requerido solo si visibility es groups o users; debe ser arreglo con al menos 1
                'targets'                       => ['required_if:visibility,groups,users','array','min:1'],

                // Cada item debe indicar su tipo
                'targets.*.target_type'         => ['bail','required', Rule::in(['group','user'])],

                // Si target_type = group: group_id es requerido y permitido; si no, queda prohibido
                'targets.*.group_id'            => [
                    'required_if:targets.*.target_type,group',
                    'prohibited_unless:targets.*.target_type,group',
                    'integer',
                    'exists:groups,id',
                ],

                // Si target_type = user: user_id es requerido y permitido; si no, queda prohibido
                'targets.*.user_id'             => [
                    'required_if:targets.*.target_type,user',
                    'prohibited_unless:targets.*.target_type,user',
                    'integer',
                    'exists:users,id',
                ],
            ]);

             $data['author_user_id'] = auth()->id();

        $announcement = DB::transaction(function () use ($data) {
            // 1) Crear el aviso (sin targets)
            $payload = collect($data)->except('targets')->all();
            /** @var Announcement $ann */
            $ann = Announcement::create($payload);

            // 2) Si aplica, crear los targets relacionados
            if (in_array($data['visibility'], ['groups','users'], true) && !empty($data['targets'])) {
                // opcional: eliminar duplicados (type+id)
                $seen = [];
                $rows = [];
                foreach ($data['targets'] as $t) {
                    $key = $t['target_type'] === 'group'
                        ? 'group:'.$t['group_id']
                        : 'user:'.$t['user_id'];

                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;

                    $rows[] = [
                        'target_type'     => $t['target_type'],
                        'group_id'        => $t['target_type'] === 'group' ? $t['group_id'] : null,
                        'user_id'         => $t['target_type'] === 'user'  ? $t['user_id']  : null,
                    ];
                }

                if ($rows) {
                    // Usa la relación
                    $ann->targets()->createMany($rows);
                }
            }

            return $ann;
        });

        // Devuelve con relaciones útiles
        return response()->json(
            $announcement->load([
                'author:id,name,email,avatar_path',
                'targets.group:id,name,grade,section,code',
                'targets.user:id,name,avatar_path',
            ]),
            201
        );

    } catch (ValidationException $e) {
        return response()->json([
            'response_code' => 422,
            'status'        => 'error',
            'message'       => 'La validación ha fallado',
            'errors'        => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        Log::error('Announcement store error: ' . $e->getMessage());

        return response()->json([
            'response_code' => 500,
            'status'        => 'error',
            'message'       => 'Error interno del servidor: ' . $e->getMessage(),
        ], 500);
    }
    }

public function show(Announcement $announcement)
{
    try {
        // Cargamos el rol del autor para obtener el name
        $announcement->load([
            'author.role:id,name',
            'targets.group:id,name,grade,section,code',
            'targets.user:id,name,avatar_path',
            'reads',
        ]);

        $author = $announcement->author
            ? [
                'id'          => $announcement->author->id,
                'name'        => $announcement->author->name,
                'email'       => $announcement->author->email,
                'avatar_path' => $announcement->author->avatar_path,
                'role'        => optional($announcement->author->role)->name,
            ]
            : null;

                // Map de targets soportando group y user
        $targets = $announcement->targets->map(function ($t) {
            if ($t->target_type === 'group' && $t->group) {
                return [
                    'id'              => $t->id,
                    'announcement_id' => $t->announcement_id,
                    'target_type'     => 'group',
                    'group' => [
                        'id'      => $t->group->id,
                        'name'    => $t->group->name,
                        'grade'   => $t->group->grade,
                        'section' => $t->group->section,
                        'code'    => $t->group->code,
                    ],
                ];
            }

            if ($t->target_type === 'user' && $t->user) {
                return [
                    'id'              => $t->id,
                    'announcement_id' => $t->announcement_id,
                    'target_type'     => 'user',
                    'user' => [
                        'id'          => $t->user->id,
                        'name'        => $t->user->name,
                        'avatar_path' => $t->user->avatar_path,
                    ],
                ];
            }

            // Fallback: por si no hay relación cargada o target desconocido
            return [
                'id'              => $t->id,
                'announcement_id' => $t->announcement_id,
                'target_type'     => $t->target_type,
                'group_id'        => $t->group_id,
                'user_id'         => $t->user_id,
            ];
        })->values();

        // Devolvemos un payload controlado
        return response()->json([
            'id'             => $announcement->id,
            'title'          => $announcement->title,
            'body_md'        => $announcement->body_md,
            'author_user_id' => $announcement->author_user_id,
            'visibility'     => $announcement->visibility,
            'published_at'   => $announcement->published_at,
            'is_archived'    => $announcement->is_archived,
            'created_at'     => $announcement->created_at,
            'updated_at'     => $announcement->updated_at,

            'author'  => $author,

            'targets'        => $targets,

            'reads' => $announcement->reads->map(function ($r) {
                return [
                    'announcement_id' => $r->announcement_id,
                    'user_id'         => $r->user_id,
                    'read_at'         => $r->read_at,
                ];
            })->values(),
        ]);

    } catch (\Exception $e) {
        Log::error('Registration Error: ' . $e->getMessage());

        return response()->json([
            'response_code' => 500,
            'status'        => 'error',
            'message'       => 'Error interno del servidor: ' . $e->getMessage(),
        ], 500);
    }
}

    public function update(Request $request, Announcement $announcement)
    {
        try{    
            $data = $request->validate([
                'title'       => ['sometimes','string','max:180'],
                'body_md'     => ['sometimes','string'],
                'visibility'  => ['sometimes', Rule::in(['all','groups','users'])],
                'published_at'=> ['nullable','date'],
                'is_archived' => ['boolean'],
            ]);

            $announcement->update($data);
            return $announcement->refresh()->load(['author','targets']);
        } catch (ValidationException $e) {
            return response()->json([
                'response_code' => 422,
                'status'        => 'error',
                'message'       => 'La validación ha fallado',
                'errors'        => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Announcement $announcement)
    {
        try{    
            $announcement->delete();
            return response()->noContent();
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Acciones rápidas
    public function publish(Announcement $announcement)
    {
        try{
            $announcement->update(['published_at' => now()]);
            return $announcement;
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function archive(Announcement $announcement)
    {
        try{    
            $announcement->update(['is_archived' => true]);
            return $announcement;
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Marcar lectura
    public function markRead(Announcement $announcement, Request $request)
    {
        try{    
            $userId = $request->user()?->id ?? $request->integer('user_id');
            $request->validate(['user_id' => ['nullable','integer','exists:users,id']]);

            AnnouncementRead::updateOrCreate(
                ['announcement_id' => $announcement->id, 'user_id' => $userId],
                ['read_at' => now()]
            );

            return response()->json(['status' => 'ok']);
        } catch (ValidationException $e) {
            return response()->json([
                'response_code' => 422,
                'status'        => 'error',
                'message'       => 'La validación ha fallado',
                'errors'        => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function history(Request $request)
{
    // Historial DEL USUARIO AUTENTICADO (sin IDs en la ruta).
    // El control de rol (admin/teacher) queda en el middleware.
    try {
        $authUser = $request->user();
        if (!$authUser) {
            abort(401, 'No autenticado');
        }

        $q = Announcement::query()
            ->where('author_user_id', $authUser->id)
            ->with([
                'author:id,name,avatar_path',
                'targets.group:id,name,grade,section,code',
                'targets.user:id,name,avatar_path',
                'reads',
            ])
            ->when($request->filled('visibility'), fn ($qq) =>
                $qq->where('visibility', $request->string('visibility'))
            )
            ->when($request->filled('published'), fn ($qq) =>
                $qq->when(
                    $request->boolean('published'),
                    fn ($qqq) => $qqq->whereNotNull('published_at'),
                    fn ($qqq) => $qqq->whereNull('published_at')
                )
            )
            ->when($request->filled('archived'), fn ($qq) =>
                $qq->where('is_archived', $request->boolean('archived'))
            );

        // Búsqueda por término (?q=...)
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $tokens  = preg_split('/\s+/u', $term) ?: [];
            $boolean = collect($tokens)
                ->filter()
                ->map(fn ($w) => '+' . trim($w, "+-@><()~*\"'") . '*')
                ->implode(' ');

            $like = '%' . str_replace(['%','_'], ['\%','\_'], $term) . '%';

            $q->where(function ($sub) use ($boolean, $like) {
                $sub->whereRaw("MATCH (title, body_md) AGAINST (? IN BOOLEAN MODE)", [$boolean])
                    ->orWhere('title', 'like', $like)
                    ->orWhere('body_md', 'like', $like);
            });
        }

        return $q->orderByDesc('published_at')
                 ->orderByDesc('id')
                 ->paginate($request->integer('per_page', 15))
                 ->withQueryString();
    } catch (\Exception $e) {
        \Log::error('Announcements history error: ' . $e->getMessage());
        return response()->json([
            'response_code' => 500,
            'status'        => 'error',
            'message'       => 'Error interno del servidor: ' . $e->getMessage(),
        ], 500);
    }
}


}
