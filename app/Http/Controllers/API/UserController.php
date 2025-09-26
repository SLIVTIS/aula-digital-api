<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Models\User;

class UserController extends Controller
{
    // GET /api/users?q=texto&per_page=15
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        // Reusa el mismo filtro para lista y para totales filtrados
        $base = User::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->string('q'));
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
                });
            });

        // Lista paginada
        $users = (clone $base)
            ->with('role:id,slug,name')
            ->orderByDesc('id')
            ->paginate($perPage);

        // Totales GLOBALes (sin filtro q)
        $all = User::selectRaw('
            COUNT(CASE WHEN role_id = 1 THEN 1 END) AS admins,
            COUNT(CASE WHEN role_id = 2 THEN 1 END) AS teachers,
            COUNT(CASE WHEN role_id = 3 THEN 1 END) AS parents
        ')->first();

        // Totales del RESULTADO FILTRADO (con q)
        $filtered = (clone $base)->selectRaw('
            COUNT(CASE WHEN role_id = 1 THEN 1 END) AS admins,
            COUNT(CASE WHEN role_id = 2 THEN 1 END) AS teachers,
            COUNT(CASE WHEN role_id = 3 THEN 1 END) AS parents
        ')->first();

        // Mantén la forma de paginate() y solo agrega "totals"
        $payload = $users->toArray();
        $payload['total_roles'] = [
            'all' => [
                'admin'   => (int) ($all->admins ?? 0),
                'teacher' => (int) ($all->teachers ?? 0),
                'parent'  => (int) ($all->parents ?? 0),
            ],
            'filtered' => [
                'admin'   => (int) ($filtered->admins ?? 0),
                'teacher' => (int) ($filtered->teachers ?? 0),
                'parent'  => (int) ($filtered->parents ?? 0),
            ],
        ];

        return response()->json($payload);
    }

    // POST /api/users
    public function store(Request $request)
    {
        try{
            $data = $request->validate([
                'name'                  => ['required','string','max:255'],
                'email'                 => ['required','email','max:255','unique:users,email'],
                'password'              => ['required','string','min:8','confirmed'],
                'role_id'               => ['required','integer','exists:roles,id'],
                'avatar'                => ['nullable','image','max:2048'], // ~2MB
            ]);

            // Crear usuario
            $user = new User();
            $user->name     = $data['name'];
            $user->email    = $data['email'];
            $user->password = Hash::make($data['password']);
            $user->role_id  = $data['role_id'];

            // Avatar (opcional)
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_path = $path;
                $user->avatar_updated_at = now();
            }

            $user->save();
            $user->load('role:id,slug,name');

            return response()->json($this->transform($user), 201);
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

    // GET /api/users/{user}
    public function show(User $user)
    {
        try{
            $user->load('role:id,slug,name');
            return response()->json($this->transform($user));
        }catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // PATCH /api/users/{user}
    public function update(Request $request, User $user)
    {
        try{
            $data = $request->validate([
                'name'     => ['sometimes','string','max:255'],
                'email'    => ['sometimes','email','max:255', Rule::unique('users','email')->ignore($user->id)],
                'role_id'  => ['sometimes','integer','exists:roles,id'],
                'avatar'   => ['nullable','image','max:2048'],
                //para permitir cambio de contraseña
                'password' => ['sometimes','string','min:8','confirmed'],
            ]);

            if (array_key_exists('name', $data))    $user->name = $data['name'];
            if (array_key_exists('email', $data))   $user->email = $data['email'];
            if (array_key_exists('role_id', $data)) $user->role_id = $data['role_id'];
            if (array_key_exists('password', $data)) $user->password = Hash::make($data['password']);

            if ($request->hasFile('avatar')) {
                // borra el anterior si existe
                if ($user->avatar_path) {
                    Storage::disk('public')->delete($user->avatar_path);
                }
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_path = $path;
                $user->avatar_updated_at = now();
            }

            $user->save();
            $user->load('role:id,slug,name');

            return response()->json($this->transform($user));

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

    // DELETE /api/users/{user}
    public function destroy(Request $request, User $user)
    {
        try{
            // Evita que un admin se elimine a sí mismo
            if ($request->user()->id === $user->id) {
                return response()->json(['message' => 'No puedes eliminar tu propio usuario.'], 422);
            }

            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->delete();

            return response()->json(['message' => 'Usuario eliminado correctamente.']);
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    // PATCH /api/users/{user}/password
    public function updatePassword(Request $request, User $user)
    {
        try{
            $data = $request->validate([
                'password' => ['required','string','min:8','confirmed'],
            ]);

            $user->password = Hash::make($data['password']);
            $user->save();

            return response()->json(['message' => 'Contraseña actualizada correctamente.']);
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

    // POST /api/users/{user}/avatar (multipart/form-data)
    public function updateAvatar(Request $request, User $user)
    {
        try{
            $data = $request->validate([
                'avatar' => ['required','image','max:2048'],
            ]);

            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_path = $path;
            $user->avatar_updated_at = now();
            $user->save();

            return response()->json([
                'message' => 'Avatar actualizado correctamente.',
                'data'    => ['avatar_path' => $user->avatar_path],
            ]);
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

    // Helper para dar una respuesta consistente
    protected function transform(User $user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'email_verified_at'  => $user->email_verified_at,
            'role'               =>  $user->role ?? null,
            'avatar_path'        => $user->avatar_path,
            'avatar_updated_at'  => $user->avatar_updated_at,
            'created_at'         => $user->created_at,
            'updated_at'         => $user->updated_at,
        ];
    }
}
