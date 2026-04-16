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
            'amount' => 310000,
            'currency' => 'ARS',
            'frequency' => 12,
            'frequency_unit' => 'months',
        ],
    ];

    public function plans(): JsonResponse
    {
        return response()->json([
            'monthly' => [
                'id' => self::PLAN_MONTHLY,
                'name' => 'Plan Mensual',
                'price' => 35000,
                'price_formatted' => '$35.000',
                'period' => 'mes',
                'features' => [
                    'Facturas ilimitadas',
                    'Productos ilimitados',
                    'Clientes ilimitados',
                    'Integración AFIP + CAE',
                    'Integración Mercado Pago',
                    'Soporte por WhatsApp',
                    'Dashboard completo',
                ],
            ],
            'annual' => [
                'id' => self::PLAN_ANNUAL,
                'name' => 'Plan Anual',
                'price' => 310000,
                'price_formatted' => '$310.000',
                'period' => 'año',
                'savings' => '$110.000 vs plan mensual',
                'features' => [
                    'Todo lo del plan mensual',
                    'Soporte prioritario WhatsApp',
                    'Dashboard con exportación',
                    '2 meses gratis incluidos',
                ],
            ],
        ]);
    }

    public function createCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:monthly,annual',
        ]);

        $user = $request->user();
        $settings = $user->settings;

        // Prioridad: token del .env (sandbox/producción global) > token guardado en BD por usuario
        $mpToken = env('MP_ACCESS_TOKEN')
            ?? $settings?->mp_access_token;

        if (!$mpToken) {
            return response()->json([
                'error' => 'Mercado Pago no está configurado. Completá la integración en Configuración.',
            ], 422);
        }

        $plan = self::PRICES[$request->plan];
        $planName = $plan['name'];
        $amount = $plan['amount'];

        // Preference para suscripción única (pago recurrente simulado)
        // Para subscripciones reales de MP se requiere cuenta de vendedor aprobada.
        // Usamos preferencia standard: un pago único por el período contratado.
        $preferenceData = [
            'items' => [
                [
                    'title' => "FactuFlash - {$planName}",
                    'description' => "Suscripción {$planName} a FactuFlash - Sistema de Facturación y Stock Pro",
                    'quantity' => 1,
                    'unit_price' => (float) $amount,
                    'currency_id' => 'ARS',
                ],
            ],
            'external_reference' => "{$user->id}-{$request->plan}",
            'notification_url' => route('subscription.webhook'),
            'back_urls' => [
                'success' => url('/#/subscription/success?plan=' . $request->plan),
                'failure' => url('/#/subscription/failure?plan=' . $request->plan),
                'pending' => url('/#/subscription/pending?plan=' . $request->plan),
            ],
            'auto_return' => 'approved',
        ];

        $response = Http::withToken($mpToken)
            ->post('https://api.mercadopago.com/checkout/preferences', $preferenceData);

        if ($response->failed()) {
            Log::error('MP preference error', ['body' => $response->body()]);
            return response()->json(['error' => 'Error al crear el pago. Verificá tu token de Mercado Pago.'], 500);
        }

        $data = $response->json();

        // Guardar intención de suscripción
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
            'checkout_url' => $data['init_point'],
            'preference_id' => $data['id'],
            'plan' => $request->plan,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $data = $request->all();

        if (!isset($data['topic']) && !isset($data['type'])) {
            return response()->json(['status' => 'ok']);
        }

        // Mercado Pago envía topic = "payment" o type = "payment"
        $paymentId = $data['data']['id'] ?? null;

        if ($paymentId) {
            $this->processPayment($paymentId);
        }

        return response()->json(['status' => 'ok']);
    }

    private function processPayment(string $paymentId): void
    {
        $mpToken = env('MP_ACCESS_TOKEN');
        if (!$mpToken) {
            Log::warning("Subscription webhook: no MP_ACCESS_TOKEN configured");
            return;
        }

        $payment = Http::withToken($mpToken)
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}")
            ->json();

        if ($payment['status'] !== 'approved') {
            return;
        }

        $externalRef = $payment['external_reference'] ?? '';
        $parts = explode('-', $externalRef);
        $userId = (int) ($parts[0] ?? 0);
        $plan = $parts[1] ?? '';

        if (!$userId) {
            Log::warning("Subscription webhook: invalid external_reference {$externalRef}");
            return;
        }

        $subscription = Subscription::where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if (!$subscription) {
            Log::warning("Subscription webhook: no pending subscription found for user {$userId}");
            return;
        }

        $subscription->status = 'active';
        $subscription->mercadopago_id = $paymentId;
        $subscription->save();
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($subscription) {
            $subscription->status = 'canceled';
            $subscription->save();
        }

        // Revertimos el estado del usuario pero dejamos la fecha de expiración intacta
        // para que siga teniendo acceso hasta que termine su tiempo pagado.
        $user->plan_status = 'trial';
        $user->save();

        return response()->json(['message' => 'Suscripción cancelada con éxito']);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = Subscription::where('user_id', $user->id)->first();

        return response()->json([
            'has_subscription' => $subscription && $subscription->status === 'active',
            'status' => $subscription?->status ?? 'none',
            'plan' => $subscription ? (
                $subscription->amount == 35000 ? self::PLAN_MONTHLY : self::PLAN_ANNUAL
            ) : null,
            'current_period_end' => $subscription?->next_payment_date?->format('Y-m-d'),
            'start_date' => $subscription?->start_date?->format('Y-m-d'),
        ]);
    }
}