<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Student;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
     public function index(Request $request)
    {
        try{
            $q = Student::query()
                ->when($request->filled('code'), fn($qq)=>$qq->where('student_code', $request->string('code')))
                ->when($request->filled('name'), function ($qq) use ($request) {
                    $name = $request->string('name')->toString();
                    $qq->where(function ($w) use ($name) {
                        $w->where('first_name', 'like', "%$name%")
                          ->orWhere('last_name', 'like', "%$name%");
                    });
                });

            return $q->orderBy('last_name')->orderBy('first_name')
                     ->paginate($request->integer('per_page', 20));
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
                'first_name'   => ['required','string','max:80'],
                'last_name'    => ['required','string','max:80'],
                'student_code' => ['nullable','string','max:40','unique:students,student_code'],
            ]);
            $s = Student::create($data);
            return response()->json($s, 201);
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

    public function show(Student $student)
    {
        try{
            return $student->loadCount(['groups','parents']);
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Student $student)
    {
        try{
            $data = $request->validate([
                'first_name'   => ['sometimes','string','max:80'],
                'last_name'    => ['sometimes','string','max:80'],
                'student_code' => ['nullable','string','max:40','unique:students,student_code,'.$student->id],
            ]);
            $student->update($data);
            return $student;
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

    public function destroy(Student $student)
    {
        try{    
            $student->delete();
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
