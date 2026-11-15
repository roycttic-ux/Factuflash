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
            return response()->json(['error' => 'subscription_required'], 402);
        }

        // 0. Si el usuario es el admin/dueño del sistema, pasa siempre
        if ($owner->email === 'admin@factuflash.com' || $owner->role === 'admin') {
            return $next($request);
        }

        // Access is bounded by verified paid time or the original seven-day trial.
        if ($owner->has_access) {
            return $next($request);
        }

        // 3. Bloquear acceso (excepto para rutas de suscripción y perfil básico)
        $allowedRoutes = [
            'api/v1/subscription/plans',
            'api/v1/subscription/checkout*',
            'api/v1/subscription/status',
            'api/v1/subscription/cancel',
            'api/v1/mp/subscription-preference',
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
            'message' => 'Tu período de acceso finalizó. Elegí un plan para continuar usando FactuFlash.'
        ], 402);
    }
}
