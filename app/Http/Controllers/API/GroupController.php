<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Group;
use Illuminate\Support\Facades\Log;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        try{
            $q = Group::query()
                ->when($request->filled('code'), fn($qq)=>$qq->where('code', $request->string('code')))
                ->when($request->filled('name'), fn($qq)=>$qq->where('name', 'like', '%'.$request->string('name').'%'));

            return $q->orderBy('name')->paginate($request->integer('per_page', 20));
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
                'name'    => ['required','string','max:120'],
                'grade'   => ['nullable','string','max:40'],
                'section' => ['nullable','string','max:40'],
                'code'    => ['required','string','max:40','unique:groups,code'],
            ]);
            $group = Group::create($data);
            return response()->json($group, 201);
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

    public function show(Group $group)
    {
        try{
            return $group->loadCount(['students','teachers']);
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Group $group)
    {
        try{
            $data = $request->validate([
                'name'    => ['sometimes','string','max:120'],
                'grade'   => ['nullable','string','max:40'],
                'section' => ['nullable','string','max:40'],
                'code'    => ['nullable','string','max:40','unique:groups,code,'.$group->id],
            ]);
            $group->update($data);
            return $group;
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

    public function destroy(Group $group)
    {
        try{
            $group->delete();
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
