<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

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
                'title'       => ['required','string','max:180'],
                'body_md'     => ['required','string'],
                'visibility'  => ['required', Rule::in(['all','groups','users'])],
                'published_at'=> ['nullable','date'],
                'is_archived' => ['boolean'],
            ]);

            $data['author_user_id'] = auth()->id(); // o pásalo en el request si no hay auth
            $ann = Announcement::create($data);

            return response()->json($ann->load('author'), 201);
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
}
