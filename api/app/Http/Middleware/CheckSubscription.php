<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Si el usuario es un empleado, chequeamos la suscripción del dueño
        $owner = $user->owner_id ? \App\Models\User::find($user->owner_id) : $user;

        if (!$owner) {
            return $next($request);
        }

        // 0. Si el usuario es el admin/dueño del sistema, pasa siempre
        if ($owner->email === 'admin@factuflash.com' || $owner->role === 'admin') {
            return $next($request);
        }

        // 1. Si el plan está activo, pasa
        if ($owner->plan_status === 'active') {
            return $next($request);
        }

        // 2. Si está en prueba y no expiró, pasa
        if ($owner->plan_status === 'trial' && $owner->trial_expires_at && $owner->trial_expires_at->isFuture()) {
            return $next($request);
        }

        // 3. Bloquear acceso (excepto para rutas de suscripción y perfil básico)
        $allowedRoutes = [
            'api/v1/subscription/plans',
            'api/v1/subscription/checkout*',
            'api/v1/subscription/status',
            'api/v1/auth/me',
            'api/v1/auth/logout',
            'api/v1/settings'
        ];

        // Check if the current route is in the allowed list
        foreach ($allowedRoutes as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'subscription_required',
            'message' => 'Tu período de prueba ha expirado. Por favor, elige un plan para continuar usando FactuFlash.'
        ], 402);
    }
}
