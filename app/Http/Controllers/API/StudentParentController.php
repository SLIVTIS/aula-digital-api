<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class StudentParentController extends Controller
{
    // Listar padres/tutores de un alumno
    public function index(Student $student)
    {
        try{
            return $student->parents()->withPivot('relationship')->get();
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Asignar padre/tutor
    public function store(Student $student, Request $request)
    {
        try{    
            $data = $request->validate([
                'parent_user_id' => ['required','integer','exists:users,id'],
                'relationship'   => ['nullable','string','max:40'],
            ]);

            $student->parents()->syncWithoutDetaching([
                $data['parent_user_id'] => ['relationship' => $data['relationship'] ?? null]
            ]);

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

    // Remover padre/tutor
    public function destroy(Student $student, User $parent)
    {
        try{
            $student->parents()->detach($parent->id);
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
