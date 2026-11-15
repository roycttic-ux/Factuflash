<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionPlans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        config(['services.mercadopago.token' => 'test-platform-token', 'services.mercadopago.webhook_secret' => null, 'app.url' => 'https://factuflash.com.ar']);
        Http::preventStrayRequests();
    }

    private function trialUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['plan_status' => 'trial', 'paid_until' => null, 'trial_expires_at' => now()->addDays(7)], $attributes));
    }

    private function subscription(User $user, string $plan = 'monthly'): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id, 'mercadopago_id' => 'PRE-123', 'plan' => $plan,
            'external_reference' => 'ff-test-reference', 'amount' => SubscriptionPlans::PLANS[$plan]['amount'],
            'status' => 'pending', 'start_date' => now(),
        ]);
    }

    private function fakeProvider(Subscription $subscription, array $paymentChanges = [], array $providerChanges = [], bool $hasPayment = true): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $payment = array_merge([
            'id' => 12345, 'status' => 'approved', 'currency_id' => 'ARS',
            'transaction_amount' => $subscription->amount, 'transaction_amount_refunded' => 0,
            'date_approved' => now()->toIso8601String(),
        ], $paymentChanges);
        Http::fake([
            'api.mercadopago.com/preapproval/PRE-123' => Http::response(array_replace_recursive([
                'id' => 'PRE-123', 'status' => 'authorized', 'external_reference' => $subscription->external_reference,
                'auto_recurring' => [
                    'currency_id' => 'ARS', 'transaction_amount' => $subscription->amount,
                    'frequency' => SubscriptionPlans::PLANS[$subscription->plan]['months'], 'frequency_type' => 'months',
                ],
            ], $providerChanges)),
            'api.mercadopago.com/authorized_payments/search*' => Http::response(['paging' => ['total' => $hasPayment ? 1 : 0], 'results' => $hasPayment ? [[
                'id' => 789, 'preapproval_id' => 'PRE-123', 'transaction_amount' => $subscription->amount,
                'currency_id' => 'ARS', 'debit_date' => $payment['date_approved'], 'payment' => ['id' => $payment['id'], 'status' => $payment['status']],
            ]] : []]),
            'api.mercadopago.com/authorized_payments/789' => Http::response(['preapproval_id' => 'PRE-123']),
            'api.mercadopago.com/v1/payments/*' => Http::response($payment),
        ]);
    }

    private function notify(string $type = 'subscription_preapproval', string $id = 'PRE-123')
    {
        return $this->postJson('/api/v1/webhooks/subscription', ['type' => $type, 'data' => ['id' => $id]]);
    }

    public function test_plans_have_the_agreed_prices(): void
    {
        $this->actingAs($this->trialUser())->getJson('/api/v1/subscription/plans')
            ->assertOk()->assertJsonPath('monthly.price', 20000)->assertJsonPath('annual.price', 240000);
    }

    public function test_trial_access_stops_exactly_at_seven_days_with_a_bearer_token(): void
    {
        $user = $this->trialUser();
        $token = $user->createToken('test')->plainTextToken;
        $this->travelTo($user->trial_expires_at->copy()->subSecond());
        $this->withToken($token)->getJson('/api/v1/products')->assertOk();
        $this->travelTo($user->trial_expires_at);
        $this->withToken($token)->getJson('/api/v1/products')->assertStatus(402);
        $this->withToken($token)->getJson('/api/v1/subscription/plans')->assertOk();
    }

    public function test_active_label_without_paid_time_does_not_bypass_expiration(): void
    {
        $user = $this->trialUser(['plan_status' => 'active', 'trial_expires_at' => now()->subDay()]);
        $this->actingAs($user)->getJson('/api/v1/products')->assertStatus(402);
    }

    public function test_employee_follows_owners_access(): void
    {
        $owner = $this->trialUser(['trial_expires_at' => now()->subDay()]);
        $cashier = User::factory()->create(['owner_id' => $owner->id, 'role' => 'cashier']);
        $this->actingAs($cashier)->getJson('/api/v1/products')->assertStatus(402);
        $owner->update(['paid_until' => now()->addMonth(), 'plan_status' => 'active']);
        $this->actingAs($cashier)->getJson('/api/v1/products')->assertOk();
        $this->actingAs($cashier)->postJson('/api/v1/subscription/checkout', ['plan' => 'monthly'])->assertForbidden();
    }

    public function test_checkout_uses_monthly_and_annual_prices_and_reuses_pending_links(): void
    {
        foreach (['monthly' => 1, 'annual' => 12] as $plan => $months) {
            Http::fake(['api.mercadopago.com/preapproval' => Http::response(['id' => 'PRE-' . $plan, 'init_point' => 'https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_id=PRE-' . $plan])]);
            $user = $this->trialUser();
            $this->actingAs($user)->postJson('/api/v1/subscription/checkout', ['plan' => $plan])->assertOk()->assertJsonPath('plan', $plan);
            $this->actingAs($user)->postJson('/api/v1/subscription/checkout', ['plan' => $plan])->assertOk();
            Http::assertSent(fn ($request) => $request['auto_recurring']['transaction_amount'] === SubscriptionPlans::PLANS[$plan]['amount']
                && $request['auto_recurring']['frequency'] === $months
                && $request['back_url'] === 'https://factuflash.com.ar/app/#/suscripcion/exito?plan=' . $plan);
            $this->assertEquals(1, $user->subscriptions()->count());
        }
        $this->assertDatabaseCount('subscriptions', 2);
    }

    public function test_legacy_checkout_uses_same_annual_price(): void
    {
        Http::fake(['api.mercadopago.com/preapproval' => Http::response(['id' => 'PRE-LEGACY', 'init_point' => 'https://www.mercadopago.com.ar/checkout'])]);
        $this->actingAs($this->trialUser())->postJson('/api/v1/mp/subscription-preference', ['plan' => 'yearly'])->assertOk()->assertJsonPath('plan', 'annual');
        Http::assertSent(fn ($request) => $request['auto_recurring']['transaction_amount'] === 240000);
    }

    public function test_invalid_plan_returns_validation_error(): void
    {
        $this->actingAs($this->trialUser())->postJson('/api/v1/subscription/checkout', ['plan' => 'free'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_preapproval_without_payment_does_not_activate_subscription(): void
    {
        $user = $this->trialUser(['trial_expires_at' => now()->subDay()]);
        $this->fakeProvider($this->subscription($user), hasPayment: false);
        $this->notify()->assertOk();
        $this->assertNull($user->fresh()->paid_until);
        $this->actingAs($user)->getJson('/api/v1/subscription/status')->assertOk()->assertJsonPath('has_subscription', false);
        $this->actingAs($user)->getJson('/api/v1/products')->assertStatus(402);
    }

    public function test_monthly_payment_survives_trial_then_expires_after_paid_month(): void
    {
        $user = $this->trialUser();
        $originalTrial = $user->trial_expires_at->copy();
        $this->fakeProvider($this->subscription($user));
        $this->notify('subscription_authorized_payment', '789')->assertOk();
        $user->refresh();
        $end = now()->addMonthNoOverflow();
        $this->assertTrue($end->equalTo($user->paid_until));
        $this->assertTrue($originalTrial->equalTo($user->trial_expires_at));
        $this->travelTo($originalTrial);
        $this->actingAs($user)->getJson('/api/v1/products')->assertOk();
        $this->travelTo($end);
        $this->actingAs($user)->getJson('/api/v1/products')->assertStatus(402);
    }

    public function test_annual_payment_grants_twelve_months_and_duplicate_notifications_do_not_extend_it(): void
    {
        $user = $this->trialUser();
        $this->fakeProvider($this->subscription($user, 'annual'));
        $this->notify()->assertOk();
        $end = $user->fresh()->paid_until;
        $this->assertTrue(now()->addYearNoOverflow()->equalTo($end));
        $this->travel(8)->days();
        $this->notify()->assertOk();
        $this->assertTrue($end->equalTo($user->fresh()->paid_until));
        $this->assertDatabaseCount('subscription_payments', 1);
        $this->actingAs($user->fresh())->getJson('/api/v1/products')->assertOk();
        $this->travelTo($end);
        $this->actingAs($user->fresh())->getJson('/api/v1/products')->assertStatus(402);
    }

    public function test_rejected_wrong_amount_wrong_currency_and_refunded_payments_never_activate(): void
    {
        foreach ([['status' => 'rejected'], ['transaction_amount' => 1], ['currency_id' => 'USD'], ['transaction_amount_refunded' => 1]] as $changes) {
            $user = $this->trialUser(['trial_expires_at' => now()->subDay()]);
            $subscription = $this->subscription($user);
            $this->fakeProvider($subscription, $changes);
            app(\App\Services\SubscriptionBilling::class)->sync($subscription);
            $this->assertNull($user->fresh()->paid_until);
        }
    }

    public function test_webhook_retries_on_provider_failure_and_ignores_unknown_ids(): void
    {
        $this->notify('subscription_preapproval', 'UNKNOWN')->assertOk();
        Http::assertNothingSent();
        $this->subscription($this->trialUser());
        Http::fake(['api.mercadopago.com/*' => Http::response([], 503)]);
        $this->notify()->assertStatus(503);
    }

    public function test_forged_provider_reference_does_not_activate_another_user(): void
    {
        $user = $this->trialUser();
        $this->fakeProvider($this->subscription($user), providerChanges: ['external_reference' => 'someone-else']);
        $this->notify()->assertStatus(503);
        $this->assertNull($user->fresh()->paid_until);
    }

    public function test_status_reconciles_a_paid_subscription_if_webhook_was_missed(): void
    {
        $user = $this->trialUser(['trial_expires_at' => now()->subDay()]);
        $this->fakeProvider($this->subscription($user));
        $this->actingAs($user)->getJson('/api/v1/subscription/status')->assertOk()
            ->assertJsonPath('has_subscription', true)->assertJsonPath('status', 'active')->assertJsonPath('verification_pending', false);
    }

    public function test_cancel_retains_paid_period_and_refund_revokes_it(): void
    {
        $user = $this->trialUser(['trial_expires_at' => now()->subDay()]);
        $subscription = $this->subscription($user);
        $this->fakeProvider($subscription);
        $this->notify()->assertOk();
        $end = $user->fresh()->paid_until;
        $this->actingAs($user->fresh())->postJson('/api/v1/subscription/cancel')->assertOk();
        $this->assertTrue($end->equalTo($user->fresh()->paid_until));
        $this->actingAs($user->fresh())->getJson('/api/v1/products')->assertOk();
        $this->fakeProvider($subscription, ['status' => 'refunded', 'transaction_amount_refunded' => 20000]);
        $this->notify()->assertOk();
        $this->assertFalse($user->fresh()->has_access);
    }

    public function test_days_left_is_not_rounded_down_before_exact_expiration(): void
    {
        $user = $this->trialUser(['trial_expires_at' => now()->addMinute()]);
        $this->assertSame(1, $user->days_left);
        $this->travel(1)->minutes();
        $this->assertSame(0, $user->days_left);
    }

    public function test_failed_renewal_does_not_remove_already_paid_time_and_next_payment_extends_it(): void
    {
        $user = $this->trialUser();
        $subscription = $this->subscription($user);
        $this->fakeProvider($subscription);
        $this->notify()->assertOk();
        $firstEnd = $user->fresh()->paid_until->copy();
        $this->travel(15)->days();
        $this->fakeProvider($subscription, ['id' => 777, 'status' => 'rejected']);
        $this->notify()->assertOk();
        $this->assertTrue($firstEnd->equalTo($user->fresh()->paid_until));

        $this->travelTo($firstEnd);
        // The next authorized invoice has a distinct provider payment ID.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval/PRE-123' => Http::response([
                'id' => 'PRE-123', 'status' => 'authorized', 'external_reference' => $subscription->external_reference,
                'auto_recurring' => ['currency_id' => 'ARS', 'transaction_amount' => 20000, 'frequency' => 1, 'frequency_type' => 'months'],
            ]),
            'api.mercadopago.com/authorized_payments/search*' => Http::response(['results' => [[
                'preapproval_id' => 'PRE-123', 'transaction_amount' => 20000, 'currency_id' => 'ARS',
                'debit_date' => now()->toIso8601String(), 'payment' => ['id' => 67890],
            ]]]),
            'api.mercadopago.com/v1/payments/67890' => Http::response([
                'id' => 67890, 'status' => 'approved', 'currency_id' => 'ARS', 'transaction_amount' => 20000,
                'date_approved' => now()->toIso8601String(),
            ]),
        ]);
        $this->notify()->assertOk();
        $this->assertTrue(now()->addMonthNoOverflow()->equalTo($user->fresh()->paid_until));
        $this->assertDatabaseCount('subscription_payments', 2);
    }

    public function test_configured_webhook_signature_is_required_and_verified(): void
    {
        config(['services.mercadopago.webhook_secret' => 'test-secret']);
        $user = $this->trialUser();
        $this->fakeProvider($this->subscription($user));
        $this->notify()->assertUnauthorized();
        Http::assertNothingSent();
        $timestamp = (string) now()->timestamp;
        $manifest = "id:pre-123;request-id:test-request;ts:{$timestamp};";
        $hash = hash_hmac('sha256', $manifest, 'test-secret');
        $this->withHeaders(['x-request-id' => 'test-request', 'x-signature' => "ts={$timestamp},v1={$hash}"])
            ->notify()->assertOk();
        $this->assertNotNull($user->fresh()->paid_until);
    }
}
