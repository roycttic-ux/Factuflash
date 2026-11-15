<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'company_name' => 'nullable|string|max:150',
            'expected_monthly_invoices' => 'nullable|integer|min:0|max:100000000',
            'simultaneous_registers' => 'nullable|integer|min:1|max:100000',
            'captcha_token' => 'required|string',
        ]);

        if (($validated['expected_monthly_invoices'] ?? 0) > 1000 || ($validated['simultaneous_registers'] ?? 1) > 3) {
            return response()->json([
                'message' => 'Para más de 1.000 facturas por mes o más de 3 cajas simultáneas, contactanos para una solución personalizada.',
                'contact_url' => 'https://wa.me/5492954326436',
            ], 422);
        }
        if (!$this->verifyTurnstile($validated['captcha_token'])) {
             throw ValidationException::withMessages([
                'captcha' => ['La validación de seguridad (Captcha) ha fallado. Inténtelo de nuevo.'],
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'owner', // Asignar el rol de dueño por defecto
            'company_name' => $validated['company_name'] ?? null,
            'expected_monthly_invoices' => $validated['expected_monthly_invoices'] ?? null,
            'simultaneous_registers' => $validated['simultaneous_registers'] ?? null,
            'plan_status' => 'trial',
            'trial_expires_at' => now()->addDays(\App\Services\SubscriptionPlans::TRIAL_DAYS),
            'api_token' => \Illuminate\Support\Str::random(60),
        ]);

        $user->settings()->create([
            'company_name' => $validated['company_name'] ?? null,
            'show_whatsapp_button' => true
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'captcha_token' => 'required|string|max:2048',
        ]);

        if (!$this->verifyTurnstile($validated['captcha_token'])) {
             throw ValidationException::withMessages([
                'captcha' => ['La validación de seguridad ha fallado.'],
             ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout exitoso']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->api_token) {
            $user->api_token = \Illuminate\Support\Str::random(60);
            $user->save();
        }
        $user->load('settings');

        return response()->json(['user' => $user]);
    }

    private function verifyTurnstile(?string $token): bool
    {
        if (empty($token)) return false;

        try {
            $secret = config('services.turnstile.secret');
            
            if (empty($secret)) return false;

            $response = Http::timeout(5)->asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            return $response->successful() && $response->json('success') === true;
        } catch (\Exception $e) {
            \Log::warning('Turnstile verification unavailable.');
            return false;
        }
    }
}
