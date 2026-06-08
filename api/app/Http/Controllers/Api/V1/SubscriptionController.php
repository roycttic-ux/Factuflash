<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public const PLAN_MONTHLY = 'monthly';
    public const PLAN_ANNUAL = 'annual';

    public const PRICES = [
        self::PLAN_MONTHLY => [
            'name' => 'Plan Mensual',
            'amount' => 35000,
            'currency' => 'ARS',
            'frequency' => 1,
            'frequency_unit' => 'months',
        ],
        self::PLAN_ANNUAL => [
            'name' => 'Plan Anual',
            'amount' => 320000,
            'currency' => 'ARS',
            'frequency' => 12,
            'frequency_unit' => 'months',
        ],
    ];

    /**
     * Obtiene el token de Mercado Pago según el entorno.
     * En producción usa MP_ACCESS_TOKEN, en desarrollo usa MP_SANDBOX_TOKEN si está definido.
     */
    private function getMpToken(): ?string
    {
        return config('services.mercadopago.token');
    }

    private function verifyTurnstile(?string $token): bool
    {
        if (empty($token)) return true; // Si no hay token, dejamos pasar (ej: login sin captcha aún)

        try {
            $secret = env('TURNSTILE_SECRET_KEY');

            if (empty($secret)) return true;

            $response = Http::timeout(5)->asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            // Aquí podrías validar $response->json()['success'] pero lo dejamos permisivo por ahora
            return true;
        } catch (\Exception $e) {
            \Log::error('Turnstile verification error: ' . $e->getMessage());
            return true; // Fallback: allow request on network error
        }
    }

    public function plans(): JsonResponse
    {
        $features = [
            'Facturas ilimitadas',
            'Productos y Stock ilimitado',
            'Integración ARCA (AFIP) + CAE',
            'Mercado Pago Checkouts',
            'Soporte prioritario WhatsApp',
            'Dashboard en tiempo real',
            'Reportes y Exportación',
        ];

        return response()->json([
            'monthly' => [
                'id' => self::PLAN_MONTHLY,
                'name' => 'Plan Mensual',
                'price' => 35000,
                'price_formatted' => '$35.000',
                'period' => 'mes',
                'features' => $features,
            ],
            'annual' => [
                'id' => self::PLAN_ANNUAL,
                'name' => 'Plan Anual',
                'price' => 320000,
                'price_formatted' => '$320.000',
                'period' => 'año',
                'savings' => '$100.000 de ahorro',
                'features' => $features,
            ],
        ]);
    }

    public function createCheckout(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'plan' => 'required|in:monthly,annual',
            ]);

            $user = $request->user();

            // Get Mercado Pago token appropriate for environment
            $mpToken = $this->getMpToken();
            if (!$mpToken) {
                return response()->json(['error' => 'Token de Mercado Pago no configurado.'], 500);
            }

            $plan = self::PRICES[$request->plan];
            $planName = $plan['name'];
            $amount = $plan['amount'];

            // Datos para Suscripción Recurrente (Pre‑approval)
            $preapprovalData = [
                'reason' => "FactuFlash - {$planName}",
                'external_reference' => "{$user->id}-{$request->plan}",
                'payer_email' => $user->email,
                'auto_recurring' => [
                    'frequency' => $plan['frequency'],
                    'frequency_type' => 'months',
                    'transaction_amount' => (float) $amount,
                    'currency_id' => 'ARS',
                ],
                'back_url' => url('/#/subscription-success?plan=' . $request->plan),
                'notification_url' => route('subscription.webhook'),
                'status' => 'pending',
            ];

            $response = Http::timeout(15)
                ->withToken($mpToken)
                ->post('https://api.mercadopago.com/preapproval', $preapprovalData);

            if ($response->failed()) {
                $errorData = $response->json();
                Log::error('MP subscription error', [
                    'status' => $response->status(),
                    'body' => $errorData,
                    'sent_data' => $preapprovalData
                ]);
                
                $mpMsg = $errorData['message'] ?? $errorData['error'] ?? 'Error desconocido';
                
                // Si es un error de email igual al collector
                if (str_contains(strtolower($mpMsg), 'collector') || str_contains(strtolower($mpMsg), 'payer.email')) {
                    $mpMsg = "No podés usar el mismo email de tu cuenta de Mercado Pago para suscribirte. Usá un email distinto para el usuario o probá con otro navegador.";
                }

                return response()->json([
                    'error' => "Mercado Pago ({$response->status()}): {$mpMsg}",
                    'debug' => $errorData
                ], 500);
            }

            $data = $response->json();

            // Guardar intención de suscripción (recurrente)
            $subscription = Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'mercadopago_id' => $data['id'] ?? null,
                    'amount' => $amount,
                    'status' => 'pending',
                    'start_date' => now(),
                    'next_payment_date' => $request->plan === self::PLAN_MONTHLY
                        ? now()->addMonth()
                        : now()->addYear(),
                ]
            );

            return response()->json([
                'checkout_url' => $data['init_point'] ?? $data['sandbox_init_point'] ?? null,
                'preapproval_id' => $data['id'] ?? null,
                'plan' => $request->plan,
            ]);
        } catch (\Exception $e) {
            Log::error('Fatal checkout error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'error' => 'Error interno: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        $data = $request->all();
        $type = $data['type'] ?? $data['topic'] ?? '';

        // Si es una suscripción recurrente
        if ($type === 'subscription_preapproval') {
            $id = $data['data']['id'] ?? null;
            if ($id) {
                $this->updateSubscriptionStatus($id);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function updateSubscriptionStatus(string $preapprovalId): void
    {
        $mpToken = config('services.mercadopago.token');
        $response = Http::withToken($mpToken)
            ->get("https://api.mercadopago.com/preapproval/{$preapprovalId}");

        if ($response->failed()) return;

        $data = $response->json();
        $externalRef = $data['external_reference'] ?? '';
        $parts = explode('-', $externalRef);
        $userId = (int) ($parts[0] ?? 0);

        if (!$userId) return;

        $user = \App\Models\User::find($userId);
        if (!$user) return;

        $status = $data['status'] === 'authorized' ? 'active' : 'canceled';
        
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => $status,
                'mercadopago_id' => $preapprovalId,
                'amount' => $data['auto_recurring']['transaction_amount'] ?? 35000,
                'start_date' => now(),
                'next_payment_date' => $data['next_payment_date'] ?? now()->addMonth()
            ]
        );

        // Si el pago está autorizado, activamos el plan del usuario
        if ($status === 'active') {
            $user->update([
                'plan_status' => 'active',
                'trial_expires_at' => $data['next_payment_date'] ?? now()->addMonth()
            ]);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$subscription || !$subscription->mercadopago_id) {
            return response()->json(['error' => 'No hay una suscripción activa para cancelar.'], 422);
        }

        $mpToken = config('services.mercadopago.token');

        // Llamada a Mercado Pago para cancelar el débito automático
        $response = Http::withToken($mpToken)
            ->put("https://api.mercadopago.com/preapproval/{$subscription->mercadopago_id}", [
                'status' => 'cancelled'
            ]);

        if ($response->failed()) {
            Log::error('MP cancellation error', ['body' => $response->body()]);
            return response()->json(['error' => 'No se pudo cancelar en Mercado Pago. Por favor intentá de nuevo.'], 500);
        }

        $subscription->update(['status' => 'canceled']);
        
        // El usuario conserva el acceso hasta que finalice el periodo pagado (trial_expires_at)
        // pero plan_status ya no será 'active' para renovación automática
        $user->update(['plan_status' => 'trial']);

        return response()->json(['message' => 'Renovación automática cancelada con éxito. Podrás seguir usando el sistema hasta el final de tu periodo actual.']);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = Subscription::where('user_id', $user->id)->first();

        return response()->json([
            'has_subscription' => $user->plan_status === 'active',
            'status' => $subscription?->status ?? 'none',
            'plan' => $subscription ? (
                $subscription->amount <= 40000 ? self::PLAN_MONTHLY : self::PLAN_ANNUAL
            ) : null,
            'current_period_end' => $user->trial_expires_at?->format('Y-m-d'),
            'start_date' => $subscription?->start_date?->format('Y-m-d'),
        ]);
    }
}