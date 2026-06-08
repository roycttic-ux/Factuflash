<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get list of all users and their subscription status.
     * Only accessible by admin users.
     */
    public function users(Request $request)
    {
        // Check if the current user is an admin or the owner
        if ($request->user()->role !== 'admin' && $request->user()->email !== 'admin@factuflash.com') {
            return response()->json(['message' => 'Unauthorized. Admin role required.'], 403);
        }

        $users = User::select([
            'users.id',
            'users.name',
            'users.email',
            'users.company_name',
            'users.role',
            'users.plan_status',
            'users.trial_expires_at',
            'users.created_at',
            'users.owner_id',
            'owners.name as owner_name'
        ])
        ->leftJoin('users as owners', 'users.owner_id', '=', 'owners.id')
        ->orderBy('users.created_at', 'desc')
        ->get();

        return response()->json($users);
    }

    public function deleteUser($id)
    {
        if (auth()->user()->role !== 'admin' && auth()->user()->email !== 'admin@factuflash.com') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::findOrFail($id);
        
        // Don't allow deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'No puedes borrar tu propia cuenta de administrador'], 400);
        }

        $user->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    public function activateSubscription($id, Request $request)
    {
        if ($request->user()->role !== 'admin' && $request->user()->email !== 'admin@factuflash.com') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::findOrFail($id);
        $plan = $request->input('plan', 'monthly');
        
        $user->plan_status = 'active';
        $user->trial_expires_at = $plan === 'annual' ? now()->addYear() : now()->addMonth();
        $user->save();

        return response()->json([
            'message' => 'Suscripción activada manualmente (' . $plan . ')',
            'new_date' => $user->trial_expires_at
        ]);
    }
}
