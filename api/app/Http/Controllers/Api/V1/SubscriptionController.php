<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionBilling;
use App\Services\SubscriptionPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        $features = ['Facturas ilimitadas', 'Productos y Stock ilimitado', 'Integración ARCA (AFIP) + CAE',
            'Cobros con posnet registrados manualmente', 'Soporte prioritario WhatsApp', 'Dashboard en tiempo real', 'Reportes y Exportación'];
        $plans = [];
        foreach (SubscriptionPlans::PLANS as $id => $plan) {
            $plans[$id] = [
                'id' => $id, 'name' => $plan['name'], 'price' => $plan['amount'],
                'price_formatted' => '$' . number_format($plan['amount'], 0, ',', '.'),
                'period' => $plan['period'], 'features' => $features,
            ];
        }
        return response()->json($plans);
    }

    public function createCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate(['plan' => 'required|in:monthly,annual']);
        abort_if($request->user()->owner_id, 403, 'Solo el titular puede contratar una suscripción.');
        $token = config('services.mercadopago.token');
        if (!$token) return response()->json(['error' => 'El cobro de FactuFlash no está configurado. Contactá a soporte.'], 503);

        $lock = Cache::lock('subscription-checkout:' . $request->user()->id, 60);
        if (!$lock->get()) return response()->json(['error' => 'Ya hay un pago en preparación. Intentá nuevamente en unos segundos.'], 409);
        try {
            $user = $request->user();
            $planId = $validated['plan'];
            $plan = SubscriptionPlans::PLANS[$planId];
            $existing = $user->subscriptions()->whereIn('status', ['pending', 'authorized', 'active', 'paused'])->latest('id')->first();
            if ($existing) {
                if (in_array($existing->status, ['authorized', 'active', 'paused'], true)) {
                    return response()->json(['error' => 'Ya tenés una suscripción. Cancelá su renovación antes de contratar otro plan.'], 409);
                }
                if ($existing->plan === $planId && $existing->checkout_url
                    && (float) $existing->amount === (float) $plan['amount']) {
                    return $this->checkoutResponse($existing);
                }
                if ($existing->mercadopago_id) {
                    Http::withToken($token)->timeout(15)->put('https://api.mercadopago.com/preapproval/' . rawurlencode($existing->mercadopago_id), ['status' => 'cancelled'])->throw();
                }
                $existing->update(['status' => 'canceled']);
            }

            $reference = 'ff-' . $user->id . '-' . Str::uuid();
            $frontend = rtrim(config('services.mercadopago.frontend_url') ?: rtrim(config('app.url'), '/') . '/app', '/');
            $response = Http::withToken($token)->timeout(20)
                ->post('https://api.mercadopago.com/preapproval', [
                    'reason' => 'FactuFlash - ' . $plan['name'],
                    'external_reference' => $reference,
                    'payer_email' => $user->email,
                    'auto_recurring' => [
                        'frequency' => $plan['months'], 'frequency_type' => 'months',
                        'transaction_amount' => $plan['amount'], 'currency_id' => 'ARS',
                    ],
                    'back_url' => $frontend . '/#/suscripcion/exito?plan=' . $planId,
                    'notification_url' => route('subscription.webhook'),
                    'status' => 'pending',
                ]);
            if (!$response->successful()) {
                Log::warning('Subscription checkout rejected', ['http_status' => $response->status()]);
                return response()->json(['error' => 'Mercado Pago no pudo iniciar la suscripción. Revisá tu correo y volvé a intentar; si persiste, contactá a soporte.'], 502);
            }
            $data = $response->json();
            if (empty($data['id']) || empty($data['init_point'])) {
                return response()->json(['error' => 'Mercado Pago no devolvió un enlace de pago válido.'], 502);
            }
            $subscription = Subscription::create([
                'user_id' => $user->id, 'plan' => $planId, 'amount' => $plan['amount'],
                'status' => 'pending', 'mercadopago_id' => $data['id'],
                'external_reference' => $reference, 'checkout_url' => $data['init_point'],
                'start_date' => now(),
            ]);
            return $this->checkoutResponse($subscription);
        } catch (\Throwable $e) {
            Log::warning('Subscription checkout unavailable', ['exception' => get_class($e)]);
            return response()->json(['error' => 'No se pudo conectar con Mercado Pago. Intentá nuevamente en unos minutos.'], 502);
        } finally {
            $lock->release();
        }
    }

    private function checkoutResponse(Subscription $subscription): JsonResponse
    {
        return response()->json([
            'checkout_url' => $subscription->checkout_url,
            'init_point' => $subscription->checkout_url,
            'preapproval_id' => $subscription->mercadopago_id,
            'plan' => $subscription->plan,
        ]);
    }

    public function webhook(Request $request, SubscriptionBilling $billing): JsonResponse
    {
        $type = $request->input('type', $request->input('topic'));
        if (!in_array($type, ['subscription_preapproval', 'subscription_authorized_payment', 'payment'], true)) {
            return response()->json(['status' => 'ignored']);
        }
        $id = $request->input('data.id', $request->input('id', $request->query('data_id')));
        if (!is_scalar($id) || !preg_match('/^[A-Za-z0-9_-]+$/', (string) $id)) {
            return response()->json(['error' => 'Notificación inválida.'], 422);
        }
        if (!$this->validSignature($request, (string) $id)) return response()->json(['error' => 'Firma inválida.'], 401);
        try {
            $ids = [];
            if ($type === 'subscription_preapproval') {
                $ids[] = (string) $id;
            } elseif ($type === 'subscription_authorized_payment') {
                $authorized = $billing->get('authorized_payments/' . rawurlencode((string) $id));
                $ids[] = $authorized['preapproval_id'] ?? '';
            } else {
                $payments = $billing->get('authorized_payments/search', ['payment_id' => $id]);
                $ids = array_column($payments['results'] ?? [], 'preapproval_id');
            }
            foreach (array_unique($ids) as $providerId) {
                $subscription = Subscription::where('mercadopago_id', $providerId)->first();
                if ($subscription) $billing->sync($subscription);
            }
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::warning('Subscription webhook verification failed', ['exception' => get_class($e)]);
            return response()->json(['error' => 'No se pudo verificar el pago. Reintentar notificación.'], 503);
        }
    }

    private function validSignature(Request $request, string $id): bool
    {
        $secret = config('services.mercadopago.webhook_secret');
        if (!$secret) return true; // Provider API verification is always mandatory.
        $parts = [];
        foreach (explode(',', (string) $request->header('x-signature')) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) $parts[$pair[0]] = $pair[1];
        }
        if (empty($parts['ts']) || empty($parts['v1']) || !$request->header('x-request-id')) return false;
        $manifest = 'id:' . strtolower($id) . ';request-id:' . $request->header('x-request-id') . ';ts:' . $parts['ts'] . ';';
        return hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']);
    }

    public function cancel(Request $request): JsonResponse
    {
        abort_if($request->user()->owner_id, 403, 'Solo el titular puede cancelar una suscripción.');
        $subscription = $request->user()->subscriptions()
            ->whereIn('status', ['active', 'authorized', 'pending', 'paused'])->latest('id')->first();
        if (!$subscription?->mercadopago_id) return response()->json(['error' => 'No hay una renovación para cancelar.'], 422);
        try {
            Http::withToken(config('services.mercadopago.token'))->timeout(15)
                ->put('https://api.mercadopago.com/preapproval/' . rawurlencode($subscription->mercadopago_id), ['status' => 'cancelled'])->throw();
            $subscription->update(['status' => 'canceled']);
            return response()->json(['message' => 'Renovación cancelada. Conservás el acceso hasta el final del período abonado.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo cancelar en Mercado Pago. Intentá nuevamente.'], 502);
        }
    }

    public function status(Request $request, SubscriptionBilling $billing): JsonResponse
    {
        $user = $request->user()->owner_id ? \App\Models\User::findOrFail($request->user()->owner_id) : $request->user();
        $subscription = $user->subscriptions()->latest('id')->first();
        $verificationPending = false;
        if ($subscription?->mercadopago_id
            && (!$subscription->last_verified_at || $subscription->last_verified_at->lt(now()->subSeconds(10)))) {
            try {
                $billing->sync($subscription);
                $user->refresh();
            } catch (\Throwable $e) {
                $verificationPending = true;
                Log::warning('Subscription status verification unavailable', ['subscription_id' => $subscription->id, 'exception' => get_class($e)]);
            }
        }
        $paid = (bool) $user->paid_until?->isFuture();
        return response()->json([
            'has_subscription' => $paid,
            'has_access' => $user->has_access,
            'status' => $paid && $subscription?->status === 'authorized' ? 'active' : ($subscription?->status ?? 'none'),
            'plan' => $subscription?->plan,
            'amount' => $subscription?->amount,
            'current_period_end' => $user->paid_until?->toIso8601String(),
            'trial_expires_at' => $user->trial_expires_at?->toIso8601String(),
            'days_left' => $user->days_left,
            'start_date' => $subscription?->start_date?->format('Y-m-d'),
            'can_cancel' => !$request->user()->owner_id && in_array($subscription?->status, ['authorized', 'active', 'pending', 'paused'], true),
            'verification_pending' => $verificationPending,
        ]);
    }
}
