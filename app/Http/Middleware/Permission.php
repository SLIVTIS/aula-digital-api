<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class Permission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roleSlug): Response
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            abort(401); // no autenticado
        }

        $hasRole = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', $userId)
            ->where('roles.slug', $roleSlug)
            ->exists();

        if (!$hasRole) {
            abort(403, 'No autorizado');
        }

        return $next($request);
    }
}
