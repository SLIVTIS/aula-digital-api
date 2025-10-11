<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Query params:
     *  - unread_only=true|false
     *  - type=announcement.published|message.received
     *  - per_page=15 (máx 100)
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Notification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        if ($request->filled('type')) {
            $query->ofType($request->string('type'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * GET /api/notifications/{notification}
     */
    public function show(Notification $notification, Request $request): JsonResponse
    {
        $this->authorizeOwner($notification, $request);
        return response()->json($notification);
    }

    /**
     * POST /api/notifications
     * body: { user_id, type, payload_json (objeto/array) }
     * (Útil si generas notificaciones desde tu backend)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'      => ['required', 'exists:users,id'],
            'type'         => ['required', 'string', 'max:60'],
            'payload_json' => ['required', 'array'],
        ]);

        $notification = Notification::create([
            'user_id'      => $data['user_id'],
            'type'         => $data['type'],
            'payload_json' => $data['payload_json'],
            'is_read'      => false,
            // created_at lo pone la DB por defecto
        ]);

        return response()->json($notification, 201);
    }

    /**
     * POST /api/notifications/{notification}/read
     * Marca como leída una notificación del usuario autenticado.
     */
    public function markAsRead(Notification $notification, Request $request): JsonResponse
    {
        $this->authorizeOwner($notification, $request);

        if (!$notification->is_read) {
            $notification->is_read = true;
            $notification->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/notifications/read-all
     * Marca todas como leídas para el usuario autenticado.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        Notification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/notifications/{notification}
     */
    public function destroy(Notification $notification, Request $request): JsonResponse
    {
        $this->authorizeOwner($notification, $request);
        $notification->delete();

        return response()->json([], 204);
    }

    /**
     * GET /api/notifications/badge
     * Devuelve el conteo de no leídas (para el badge del UI).
     */
    public function badge(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $count = Notification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread' => $count]);
    }

    /** Verifica que la notificación pertenezca al usuario autenticado */
    protected function authorizeOwner(Notification $notification, Request $request): void
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403, 'No autorizado');
        }
    }
}
