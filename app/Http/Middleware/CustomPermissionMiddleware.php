<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomPermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super Admin bypasses all permission checks completely
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user has any of the requested permissions
        foreach ($permissions as $permission) {
            // Handle pipe-separated permissions like 'permission1|permission2'
            $subPermissions = explode('|', $permission);
            foreach ($subPermissions as $p) {
                $trimP = trim($p);
                try {
                    if ($user->can($trimP)) {
                        return $next($request);
                    }
                } catch (\Throwable $e) {}

                try {
                    if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($trimP)) {
                        return $next($request);
                    }
                } catch (\Throwable $e) {}
            }
        }

        return response()->json(['message' => 'User does not have the required permissions.'], 403);
    }
}
