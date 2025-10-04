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
        try{
            $q = Announcement::query()
                ->with(['author:id,name', 'targets', 'reads'])
                ->when($request->filled('visibility'), fn ($qq) =>
                    $qq->where('visibility', $request->string('visibility'))
                )
                ->when($request->filled('author_id'), fn ($qq) =>
                    $qq->where('author_user_id', $request->integer('author_id'))
                )
                ->when($request->filled('published'), fn ($qq) =>
                    $qq->when($request->boolean('published'), fn ($qqq) => $qqq->whereNotNull('published_at'),
                        fn ($qqq) => $qqq->whereNull('published_at'))
                );

            if ($term = $request->string('q')->toString()) {
                $q->whereFullText(['title','body_md'], $term);
            }

            return $q->orderByDesc('published_at')->orderByDesc('id')
                     ->paginate($request->integer('per_page', 15));
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
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
        try{
            return $announcement->load(['author','targets','reads']);
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
