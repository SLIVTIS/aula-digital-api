<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Group;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class GroupStudentController extends Controller
{
     // Listar alumnos de un grupo
    public function index(Group $group)
    {
        try{
            return $group->students()->orderBy('last_name')->orderBy('first_name')->get();
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Agregar alumno(s) al grupo
    public function store(Group $group, Request $request)
    {
        try{    
            $data = $request->validate([
                'student_ids'   => ['required','array','min:1'],
                'student_ids.*' => ['integer','exists:students,id'],
            ]);
            $group->students()->syncWithoutDetaching($data['student_ids']);
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

    // Quitar un alumno del grupo
    public function destroy(Group $group, Student $student)
    {
        try{
            $group->students()->detach($student->id);
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
