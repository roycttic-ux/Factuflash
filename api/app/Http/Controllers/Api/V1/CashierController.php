<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CashierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Solo el dueño (o admin) puede ver a sus cajeros
        if (!in_array($request->user()->role, ['owner', 'admin'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $cashiers = User::where('owner_id', $request->user()->id)
            ->where('role', 'cashier')
            ->orderBy('created_at', 'desc')
            ->withSum('cashierInvoices as total_recaudacion', 'total_local')
            ->withCount('cashierInvoices as total_comprobantes')
            ->with('tokens')
            ->get(['id', 'name', 'email', 'cash_register_name', 'created_at'])
            ->map(function ($c) {
                $lastToken = $c->tokens->sortByDesc('last_used_at')->first();
                $c->last_seen = $lastToken ? $lastToken->last_used_at : null;
                $c->is_online = $c->last_seen && $c->last_seen->diffInMinutes(now()) <= 15;
                unset($c->tokens);
                return $c;
            });

        return response()->json(['cashiers' => $cashiers]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!in_array($request->user()->role, ['owner', 'admin'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => 'required|string|min:6',
            'cash_register_name' => 'required|string|max:50', // Ej: Caja Fuerte, Caja Sucursal 1
        ]);

        $cashier = clone $request->user(); // Hereda configuración básica
        
        $newCashier = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'cashier',
            'owner_id' => $request->user()->id,
            'cash_register_name' => $validated['cash_register_name'],
            'company_name' => $request->user()->company_name, // Hereda la empresa
        ]);

        return response()->json(['cashier' => $newCashier], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!in_array($request->user()->role, ['owner', 'admin'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $cashier = User::where('id', $id)
            ->where('owner_id', $request->user()->id)
            ->where('role', 'cashier')
            ->first();

        if (!$cashier) {
            return response()->json(['error' => 'Cajero no encontrado'], 404);
        }

        $cashier->delete();

        return response()->json(['message' => 'Cajero eliminado correctamente. Las facturas emitidas por él seguirán intactas bajo la autoría del comercio.']);
    }
}
