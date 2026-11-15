<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:sync', function () {
    $failed = 0;
    \App\Models\Subscription::whereNotNull('mercadopago_id')
        ->whereIn('status', ['pending', 'authorized', 'active', 'paused'])
        ->orderBy('id')->each(function ($subscription) use (&$failed) {
            try {
                app(\App\Services\SubscriptionBilling::class)->sync($subscription);
            } catch (\Throwable $e) {
                $failed++;
                \Illuminate\Support\Facades\Log::warning('Subscription reconciliation failed', [
                    'subscription_id' => $subscription->id, 'exception' => get_class($e),
                ]);
            }
        });
    $this->info($failed ? "Suscripciones pendientes de reintento: {$failed}" : 'Suscripciones verificadas.');
    return $failed ? 1 : 0;
})->purpose('Reconciliar cobros con Mercado Pago ante notificaciones demoradas.');

Schedule::command('subscriptions:sync')->everyTenMinutes()->withoutOverlapping();
