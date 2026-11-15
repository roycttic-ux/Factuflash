<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PosDevice;
use App\Models\Product;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\AfipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $user, array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'user_id' => $user->id, 'invoice_number' => 'TEST-' . fake()->uuid(),
            'dollar_rate' => 1, 'total_usd' => 1500.50, 'total_local' => 1500.50,
            'status' => 'pending', 'payment_method' => 'card',
        ], $attributes));
    }

    private function device(User $user, string $type = 'traditional_pos'): PosDevice
    {
        return PosDevice::create(['user_id' => $user->id, 'name' => 'Caja', 'type' => $type, 'external_id' => fake()->uuid()]);
    }

    private function manual(Invoice $invoice, PosDevice $device, array $extra = []): array
    {
        return array_merge(['invoice_id' => $invoice->id, 'external_id' => $device->external_id,
            'amount' => 1500.50, 'transaction_id' => 'LOTE-5-OP-32', 'confirmed' => true], $extra);
    }

    private function order(Invoice $invoice, array $extra = []): array
    {
        return array_replace_recursive([
            'id' => $invoice->point_order_id, 'type' => 'point', 'country_code' => 'ARG',
            'external_reference' => 'invoice_' . $invoice->id,
            'status' => 'processed', 'status_detail' => 'processed',
            'transactions' => ['payments' => [['id' => 'PAY123', 'amount' => '1500.50', 'status' => 'processed', 'status_detail' => 'accredited']]],
        ], $extra);
    }

    public function test_register_device(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/pos-devices', [
            'name' => 'Caja', 'type' => 'traditional_pos', 'external_id' => 'ABC',
        ])->assertCreated();
    }

    public function test_manual_payment_is_persistent_and_idempotent(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);
        $device = $this->device($user);
        $payload = $this->manual($invoice, $device);
        $this->actingAs($user)->postJson('/api/v1/pos-devices/confirm', $payload)->assertOk()->assertJsonPath('invoice.status', 'paid');
        $this->postJson('/api/v1/pos-devices/confirm', $payload)->assertOk();
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertStringContainsString('Confirmación manual', $invoice->payments()->first()->details);
        $this->getJson('/api/v1/pos-devices/status/' . $device->external_id . '?invoice_id=' . $invoice->id)->assertOk()->assertJsonPath('status', 'approved');
    }

    public function test_manual_payment_rejects_wrong_amount_missing_confirmation_and_duplicate_receipt(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);
        $device = $this->device($user);
        $this->actingAs($user)->postJson('/api/v1/pos-devices/confirm', $this->manual($invoice, $device, ['amount' => 1]))->assertUnprocessable();
        $this->postJson('/api/v1/pos-devices/confirm', $this->manual($invoice, $device, ['confirmed' => false]))->assertUnprocessable();
        $this->assertSame('pending', $invoice->fresh()->status);
        $this->postJson('/api/v1/pos-devices/confirm', $this->manual($invoice, $device))->assertOk();
        $other = $this->invoice($user);
        $this->postJson('/api/v1/pos-devices/confirm', $this->manual($other, $device))->assertUnprocessable();
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_foreign_devices_and_invoices_cannot_be_confirmed_or_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $invoice = $this->invoice($owner);
        $device = $this->device($owner);
        $ownDevice = $this->device($other);
        $this->actingAs($other)->postJson('/api/v1/pos-devices/confirm', $this->manual($invoice, $device))->assertNotFound();
        $this->postJson('/api/v1/pos-devices/confirm', $this->manual($invoice, $ownDevice))->assertNotFound();
        $this->getJson('/api/v1/pos-devices/status/' . $device->external_id . '?invoice_id=' . $invoice->id)->assertNotFound();
        $this->getJson('/api/v1/mp/invoices/' . $invoice->id . '/status')->assertNotFound();
    }

    public function test_legacy_bridge_cannot_create_paid_invoices(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/pos/webhook', ['amount' => 100])->assertConflict();
        $this->assertSame(0, Invoice::count());
        $this->getJson('/api/v1/pos-devices/download-agent/windows')->assertStatus(410);
    }

    public function test_card_invoice_waits_for_payment_and_stock_is_reserved_once(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'arca_cert_path' => 'test', 'arca_key_path' => 'test', 'tax_condition' => 'Monotributista']);
        $mock = \Mockery::mock(AfipService::class);
        $mock->shouldReceive('createInvoice')->once()->andReturn(['cae' => '12345678901234', 'vencimiento' => '20261231', 'numero' => 1]);
        $this->app->bind(AfipService::class, fn () => $mock);
        $product = Product::factory()->create(['user_id' => $user->id, 'stock' => 2, 'cost_usd' => 1500.50, 'unlimited_stock' => false]);
        $response = $this->actingAs($user)->postJson('/api/v1/invoices', ['payment_method' => 'card', 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $response->assertCreated()->assertJsonPath('invoice.status', 'pending')->assertJsonCount(0, 'invoice.payments');
        $this->assertEquals(1, $product->fresh()->stock);
        $invoice = Invoice::findOrFail($response->json('invoice.id'));
        $this->postJson('/api/v1/pos-devices/confirm', $this->manual($invoice, $this->device($user)))->assertOk();
        $this->assertEquals(1, $product->fresh()->stock);
    }

    public function test_overselling_rolls_back_the_sale(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['user_id' => $user->id, 'stock' => 1, 'unlimited_stock' => false]);
        $this->actingAs($user)->postJson('/api/v1/invoices', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertUnprocessable();
        $this->assertEquals(1, $product->fresh()->stock);
        $this->assertSame(0, Invoice::count());
    }

    public function test_point_uses_invoice_balance_merchant_token_and_a_persistent_retry_key(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD123'], 201)]);
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'mp_access_token' => 'merchant-token']);
        $invoice = $this->invoice($user);
        $invoice->payments()->create(['payment_method' => 'cash', 'amount' => 500]);
        $device = $this->device($user, 'mercadopago_point');
        $payload = ['invoice_id' => $invoice->id, 'device_id' => $device->external_id, 'amount' => 1];
        $this->actingAs($user)->postJson('/api/v1/mp/point-intent', $payload)->assertOk();
        $this->postJson('/api/v1/mp/point-intent', $payload)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['transactions']['payments'][0]['amount'] === '1000.50'
            && $request['external_reference'] === 'invoice_' . $invoice->id
            && $request->hasHeader('Authorization', 'Bearer merchant-token')
            && $request->hasHeader('X-Idempotency-Key', $invoice->fresh()->point_request_key));
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_point_webhook_retrieves_order_and_confirms_only_once(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'mp_access_token' => 'merchant-token']);
        $invoice = $this->invoice($user, ['point_order_id' => 'ORD123']);
        Http::fake(['api.mercadopago.com/v1/orders/ORD123' => Http::response($this->order($invoice))]);
        $payload = ['type' => 'order', 'data' => ['id' => 'ORD123'], 'status' => 'processed'];
        $this->postJson('/api/v1/webhooks/mp', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/mp', $payload)->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame('mp:PAY123', $invoice->fresh()->payment_transaction_id);
    }

    public function test_provider_pending_wrong_reference_currency_and_wrong_amount_do_not_pay_invoice(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'mp_access_token' => 'merchant-token']);
        $invoice = $this->invoice($user, ['point_order_id' => 'ORD123']);
        foreach ([['status' => 'created'], ['external_reference' => 'invoice_999'], ['country_code' => 'BRA'], ['transactions' => ['payments' => [['amount' => '1.00']]]]] as $extra) {
            Http::fake(['api.mercadopago.com/v1/orders/ORD123' => Http::response($this->order($invoice, $extra))]);
            $this->postJson('/api/v1/webhooks/mp', ['type' => 'order', 'data' => ['id' => 'ORD123'], 'status' => 'processed']);
            $this->assertSame('pending', $invoice->fresh()->status);
            $this->assertSame(0, $invoice->payments()->count());
        }
    }

    public function test_polling_recovers_missed_webhook(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'mp_access_token' => 'merchant-token']);
        $invoice = $this->invoice($user, ['point_order_id' => 'ORD123']);
        Http::fake(['api.mercadopago.com/v1/orders/ORD123' => Http::response($this->order($invoice))]);
        $this->actingAs($user)->getJson('/api/v1/mp/invoices/' . $invoice->id . '/status')->assertOk()->assertJsonPath('invoice.status', 'paid');
    }

    public function test_a_failed_provider_request_keeps_the_invoice_pending(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'mp_access_token' => 'merchant-token']);
        $invoice = $this->invoice($user, ['point_order_id' => 'ORD123']);
        Http::fake(['api.mercadopago.com/*' => Http::response([], 503)]);
        $this->actingAs($user)->getJson('/api/v1/mp/invoices/' . $invoice->id . '/status')->assertStatus(502);
        $this->assertSame('pending', $invoice->fresh()->status);
    }
}
