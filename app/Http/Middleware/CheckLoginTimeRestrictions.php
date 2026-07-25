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

        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user && !$user->isWithinAllowedLoginTime()) {
            
            // If the user is currently outside of allowed time, return 403 Forbidden.
            return response()->json([
                'message' => 'Your shift time is completed. Please contact the Super Admin to extend your time.'
            ], 403);
        }

        return $next($request);
    }
}
