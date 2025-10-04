<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TeacherGroupController extends Controller
{
    // Listar maestros de un grupo
    public function index(Group $group)
    {
        try{
            return $group->teachers()->select('users.id','users.name','users.email')->get();
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Asignar maestro(s) a un grupo
    public function store(Group $group, Request $request)
    {
        try{    
            $data = $request->validate([
                'teacher_user_ids'   => ['required','array','min:1'],
                'teacher_user_ids.*' => ['integer','exists:users,id'],
            ]);

            $group->teachers()->syncWithoutDetaching($data['teacher_user_ids']);
            return response()->json(['status' => 'attached'], 201);
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

    // Remover maestro del grupo
    public function destroy(Group $group, User $teacher)
    {
        try{
            $group->teachers()->detach($teacher->id);
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
