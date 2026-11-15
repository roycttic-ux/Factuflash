<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SubscriptionBilling
{
    public function get(string $path, array $query = []): array
    {
        $token = config('services.mercadopago.token');
        if (!$token) throw new \RuntimeException('Mercado Pago no configurado.');
        return Http::withToken($token)->timeout(15)
            ->get('https://api.mercadopago.com/' . $path, $query)->throw()->json();
    }

    /** A preapproval authorizes future charges; only an approved payment buys access. */
    public function sync(Subscription $subscription): void
    {
        if (!$subscription->mercadopago_id) return;
        $provider = $this->get('preapproval/' . rawurlencode($subscription->mercadopago_id));
        $reference = $subscription->external_reference ?: "{$subscription->user_id}-{$subscription->plan}";
        if ((string) ($provider['id'] ?? '') !== $subscription->mercadopago_id
            || (string) ($provider['external_reference'] ?? '') !== $reference
            || ($provider['auto_recurring']['currency_id'] ?? '') !== 'ARS'
            || round((float) ($provider['auto_recurring']['transaction_amount'] ?? 0), 2) !== round((float) $subscription->amount, 2)
            || (int) ($provider['auto_recurring']['frequency'] ?? 0) !== (SubscriptionPlans::PLANS[$subscription->plan]['months'] ?? 0)
            || ($provider['auto_recurring']['frequency_type'] ?? '') !== 'months') {
            throw new \RuntimeException('Los datos de suscripción no coinciden.');
        }

        $offset = 0;
        do {
            $page = $this->get('authorized_payments/search', [
                'preapproval_id' => $subscription->mercadopago_id, 'limit' => 50, 'offset' => $offset,
            ]);
            foreach ($page['results'] ?? [] as $authorized) {
                if ((string) ($authorized['preapproval_id'] ?? '') !== $subscription->mercadopago_id) continue;
                $paymentId = data_get($authorized, 'payment.id');
                if (!$paymentId) continue;
                $payment = $this->get('v1/payments/' . rawurlencode((string) $paymentId));
                $this->recordPayment($subscription, $authorized, $payment);
            }
            $offset += 50;
        } while ($offset < (int) data_get($page, 'paging.total', 0));

        $subscription->update([
            'status' => match ($provider['status'] ?? 'pending') {
                'authorized' => 'authorized',
                'cancelled', 'canceled' => 'canceled',
                'paused' => 'paused',
                default => 'pending',
            },
            'next_payment_date' => $provider['next_payment_date'] ?? null,
            'last_verified_at' => now(),
        ]);
    }

    private function recordPayment(Subscription $subscription, array $authorized, array $payment): void
    {
        $id = (string) ($payment['id'] ?? '');
        if (!$id || $id !== (string) data_get($authorized, 'payment.id')) return;
        $approved = ($payment['status'] ?? '') === 'approved'
            && ($payment['currency_id'] ?? '') === 'ARS'
            && round((float) ($payment['transaction_amount'] ?? 0), 2) === round((float) $subscription->amount, 2)
            && round((float) ($authorized['transaction_amount'] ?? 0), 2) === round((float) $subscription->amount, 2)
            && ($authorized['currency_id'] ?? '') === 'ARS'
            && (float) ($payment['transaction_amount_refunded'] ?? 0) === 0.0;

        DB::transaction(function () use ($subscription, $authorized, $payment, $id, $approved) {
            $user = User::whereKey($subscription->user_id)->lockForUpdate()->firstOrFail();
            $existing = SubscriptionPayment::where('provider_payment_id', $id)->first();
            if ($existing && $existing->subscription_id !== $subscription->id) return;
            if ($approved) {
                if (empty($payment['date_approved'])) return;
                $paidAt = Carbon::parse($payment['date_approved']);
                // Scheduled dates keep delayed notifications and retries idempotent.
                $start = !empty($authorized['debit_date']) ? Carbon::parse($authorized['debit_date']) : $paidAt->copy();
                if ($start->isFuture() || $paidAt->isFuture()) return;
                $end = $start->copy()->addMonthsNoOverflow(SubscriptionPlans::PLANS[$subscription->plan]['months']);
                SubscriptionPayment::updateOrCreate(['provider_payment_id' => $id], [
                    'subscription_id' => $subscription->id, 'amount' => $subscription->amount,
                    'paid_at' => $paidAt, 'period_ends_at' => $end, 'revoked' => false,
                ]);
                if (!$user->paid_until || $end->greaterThan($user->paid_until)) $user->paid_until = $end;
            } elseif ($existing && (in_array($payment['status'] ?? '', ['refunded', 'charged_back', 'cancelled'], true)
                || (float) ($payment['transaction_amount_refunded'] ?? 0) > 0)) {
                $existing->update(['revoked' => true]);
                $user->paid_until = SubscriptionPayment::whereIn('subscription_id', $user->subscriptions()->select('id'))
                    ->where('revoked', false)->max('period_ends_at');
            }
            $user->plan_status = $user->paid_until?->isFuture()
                ? 'active' : ($user->trial_expires_at?->isFuture() ? 'trial' : 'expired');
            $user->save();
        });
    }
}
