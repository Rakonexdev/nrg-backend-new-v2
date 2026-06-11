<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLoginTimeRestrictions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isWithinAllowedLoginTime()) {
            // Optional: you can check if user is super admin and bypass
            // if ($user->hasRole('admin')) { return $next($request); }
            // Given the requirement "Super Admin can define flexible login timings for Admins and other users",
            // It means Super Admin might be excluded from restrictions. Let's assume if they don't have restrictions set, they are bypassed anyway (handled in model).
            
            // If the user is currently outside of allowed time, return 403 Forbidden.
            return response()->json([
                'message' => 'Your shift time is completed. Please contact the Super Admin to extend your time.'
            ], 403);
        }

        return $next($request);
    }
}
