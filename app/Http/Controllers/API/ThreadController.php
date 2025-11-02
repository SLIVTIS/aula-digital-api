<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ThreadController extends Controller
{
    /** GET /api/threads : lista de hilos del usuario autenticado */

    public function index(Request $request)
    {
        $userId = Auth::id();

        $threads = Thread::query()
            ->forUser($userId)
            ->with([
                // Participantes con solo los campos necesarios + role
                'participants:id,name,role_id,avatar_path',
                'participants.role:id,slug,name',

                // Último mensaje (limit 1) + su sender (minificado)
                'messages' => function ($q) {
                    $q->latest('created_at')->limit(1)
                    ->with([
                        'sender:id,name,role_id,avatar_path',
                        'sender.role:id,slug,name',
                    ]);
                },
            ])
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('messages.thread_id', 'threads.id')
                    ->latest('created_at')
                    ->limit(1)
            )
            ->paginate($request->integer('per_page', 20));

        return response()->json($threads);
    }

    /**
     * POST /api/threads
     * body: { is_one_to_one: bool, subject?: string, participants: [userId,...], first_message?: string }
     */
    public function store(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'is_one_to_one'  => ['required', 'boolean'],
            'subject'        => ['nullable', 'string', 'max:180'],
            'participants'   => ['required', 'array', 'min:1'],
            'participants.*' => ['integer', Rule::exists('users', 'id')],
            'first_message'  => ['nullable', 'string'],
        ]);

        // Asegurar que el creador esté incluido como participante
        $participants = collect($data['participants'])->push($userId)->unique()->values();

        if ($data['is_one_to_one']) {
            // Validar exactamente 2 participantes para 1:1
            if ($participants->count() !== 2) {
                return response()->json(['message' => 'Un hilo 1:1 debe tener exactamente 2 participantes.'], 422);
            }
            // Reutilizar si ya existe
            $otherId = $participants->first(fn($id) => $id !== $userId);
            $existing = Thread::firstOneToOneBetween($userId, $otherId);
            if ($existing) {
                return response()->json($existing->load('participants:id,name'), 200);
            }
        }

        return DB::transaction(function () use ($data, $participants, $userId) {
            $thread = Thread::create([
                'subject'       => $data['subject'] ?? null,
                'is_one_to_one' => (bool) $data['is_one_to_one'],
            ]);

            $thread->participants()->sync($participants->all());

            // Mensaje inicial opcional
            if (!empty($data['first_message'])) {
                $msg = Message::create([
                    'thread_id'      => $thread->id,
                    'sender_user_id' => $userId,
                    'body_md'        => $data['first_message'],
                    'created_at'     => now(),
                ]);
                // El emisor lo marca como leído
                DB::table('message_reads')->insert([
                    'message_id' => $msg->id,
                    'user_id'    => $userId,
                    'read_at'    => now(),
                ]);
            }

            return response()->json($thread->load('participants:id,name'), 201);
        });
    }

    /** GET /api/threads/{thread} : detalle + mensajes paginados */
    public function show(Thread $thread, Request $request)
    {
        $userId = Auth::id();

        if (!$thread->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'No perteneces a este hilo.');
        }

        // cargar participantes con role y avatar
        $thread->load([
            'participants:id,name,role_id,avatar_path',
            'participants.role:id,slug,name',
        ]);

        $messages = $thread->messages()
            ->with([
                'sender:id,name,role_id,avatar_path',
                'sender.role:id,slug,name',
            ])
            ->orderBy('created_at', 'asc')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'thread'   => $thread,
            'messages' => $messages,
        ]);
    }

    /**
     * POST /api/threads/{thread}/messages
     * body: { body_md: string }
     */
    public function storeMessage(Thread $thread, Request $request)
    {
        $userId = Auth::id();

        if (!$thread->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'No perteneces a este hilo.');
        }

        $validated = $request->validate([
            'body_md' => ['required', 'string'],
        ]);

        $msg = DB::transaction(function () use ($thread, $userId, $validated) {
            $m = Message::create([
                'thread_id'      => $thread->id,
                'sender_user_id' => $userId,
                'body_md'        => $validated['body_md'],
                'created_at'     => now(),
            ]);

            // El emisor lo marca leído automáticamente
            DB::table('message_reads')->insert([
                'message_id' => $m->id,
                'user_id'    => $userId,
                'read_at'    => now(),
            ]);

            return $m;
        });

        // TODO: emitir evento/broadcast si se llegara a usar websockets

        return response()->json($msg->load('sender:id,name'), 201);
    }

    /**
     * POST /api/threads/{thread}/read
     * Marca como leídos todos los mensajes del hilo para el usuario actual (excepto los ya leídos).
     */
    public function markRead(Thread $thread)
    {
        $userId = Auth::id();

        if (!$thread->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'No perteneces a este hilo.');
        }

        $messageIds = $thread->messages()
            ->whereDoesntHave('readers', fn($q) => $q->where('users.id', $userId))
            ->pluck('id');

        $now = now();
        $rows = $messageIds->map(fn($id) => [
            'message_id' => $id,
            'user_id'    => $userId,
            'read_at'    => $now,
        ])->all();

        if (!empty($rows)) {
            DB::table('message_reads')->insert($rows);
        }

        return response()->json(['marked' => count($rows)]);
    }

    public function unreadSummary(Request $request)
    {
        $userId = Auth::id();
        $threadId = $request->integer('thread_id'); // opcional para un hilo específico
        
        // Cuenta mensajes de hilos donde PARTICIPA el usuario, que NO ha leído
        // y que NO fueron enviados por él.
        $q = Message::query()
            ->selectRaw('messages.thread_id, COUNT(messages.id) AS unread_count, MAX(messages.created_at) AS last_unread_at')
            ->join('thread_participants as tp', function ($j) use ($userId) {
                $j->on('tp.thread_id', '=', 'messages.thread_id')
                ->where('tp.user_id', '=', $userId);
            })
            ->leftJoin('message_reads as mr', function ($j) use ($userId) {
                $j->on('mr.message_id', '=', 'messages.id')
                ->where('mr.user_id', '=', $userId);
            })
            ->whereNull('mr.message_id')
            ->where('messages.sender_user_id', '<>', $userId);

        if ($threadId) {
            $q->where('messages.thread_id', $threadId);
        }

        $rows = $q->groupBy('messages.thread_id')
            ->orderByDesc('last_unread_at')
            ->get();

        return response()->json([
            'data'  => $rows,                                // [{thread_id, unread_count, last_unread_at}, ...]
            'total' => (int) $rows->sum('unread_count'),     // total global de no leídos
        ]);
    }

    public function unreadCount(Request $request)
    {
        $userId = Auth::id();

        // Cuenta mensajes de hilos donde participa el usuario,
        // que NO ha leído y que NO fueron enviados por él.
        $total = Message::query()
            ->join('thread_participants as tp', function ($j) use ($userId) {
                $j->on('tp.thread_id', '=', 'messages.thread_id')
                ->where('tp.user_id', '=', $userId);
            })
            ->leftJoin('message_reads as mr', function ($j) use ($userId) {
                $j->on('mr.message_id', '=', 'messages.id')
                ->where('mr.user_id', '=', $userId);
            })
            ->whereNull('mr.message_id')
            ->where('messages.sender_user_id', '<>', $userId)
            ->count('messages.id');

        return response()->json(['total' => (int) $total]);
    }
}
