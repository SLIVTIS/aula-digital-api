<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class AnnouncementTargetController extends Controller
{
    // Listar targets de un anuncio
    public function index(Announcement $announcement)
    {
        try{
            return $announcement->targets()->get();
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Agregar target (group|user)
    public function store(Announcement $announcement, Request $request)
    {
        try{    
            $data = $request->validate([
                'target_type' => ['required', Rule::in(['group','user'])],
                'group_id'    => ['nullable','integer','exists:groups,id'],
                'user_id'     => ['nullable','integer','exists:users,id'],
            ]);

            // Validación lógica como el CHECK de la BD
            if ($data['target_type'] === 'group' && empty($data['group_id'])) {
                return response()->json(['message' => 'group_id requerido'], 422);
            }
            if ($data['target_type'] === 'user' && empty($data['user_id'])) {
                return response()->json(['message' => 'user_id requerido'], 422);
            }

            $data['announcement_id'] = $announcement->id;
            $target = AnnouncementTarget::create($data);

            return response()->json($target, 201);
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

    // Eliminar un target específico
    public function destroy(Announcement $announcement, AnnouncementTarget $target)
    {
        try{    
            abort_unless($target->announcement_id === $announcement->id, 404);
            $target->delete();
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
}
