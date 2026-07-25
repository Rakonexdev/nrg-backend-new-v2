<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (class_exists(\App\Http\Middleware\HandleCorsHeaders::class)) {
            $middleware->append(\App\Http\Middleware\HandleCorsHeaders::class);
        }

        $permissionMiddleware = class_exists(\App\Http\Middleware\CustomPermissionMiddleware::class)
            ? \App\Http\Middleware\CustomPermissionMiddleware::class
            : \Spatie\Permission\Middleware\PermissionMiddleware::class;

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => $permissionMiddleware,
            'can' => $permissionMiddleware,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check_login_time' => \App\Http\Middleware\CheckLoginTimeRestrictions::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });
    })->create();
