<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\AfipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualInvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function sale(): array
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        UserSetting::create([
            'user_id' => $user->id, 'arca_cert_path' => 'test', 'arca_key_path' => 'test',
            'tax_condition' => 'Monotributista', 'arca_cuit' => '20451097025',
        ]);
        $product = Product::factory()->create([
            'user_id' => $user->id, 'cost_usd' => 1500.50, 'stock' => 5, 'unlimited_stock' => false,
        ]);
        $mock = \Mockery::mock(AfipService::class);
        $mock->shouldReceive('createInvoice')->once()->andReturn([
            'cae' => '12345678901234', 'vencimiento' => '20261231', 'numero' => 1,
        ]);
        $this->app->bind(AfipService::class, fn () => $mock);
        $this->actingAs($user);
        return [$user, $product, [
            'submission_key' => (string) Str::uuid(),
            'manual_payment_confirmed' => true,
            'payment_method' => 'card',
            'payments' => [['payment_method' => 'card', 'amount' => 1500.50, 'reference' => 'Payway caja1 20/10/26 lote 5 op 32']],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]];
    }

    public function test_manual_pos_sale_is_paid_without_provider_calls_and_retry_issues_only_once(): void
    {
        [$user, $product, $payload] = $this->sale();
        $response = $this->postJson('/api/v1/invoices', $payload)
            ->assertCreated()->assertJsonPath('invoice.status', 'paid')->assertJsonCount(1, 'invoice.payments');
        $this->postJson('/api/v1/invoices', $payload)->assertCreated()->assertJsonPath('invoice.id', $response->json('invoice.id'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Invoice::first()->payments()->count());
        $this->assertEquals(4, $product->fresh()->stock);
        $this->assertStringContainsString('Confirmación manual', $response->json('invoice.payments.0.details'));
        Http::assertNothingSent();
    }

    public function test_mixed_manual_payments_record_each_receipt_and_reject_reuse(): void
    {
        [$user, $product, $payload] = $this->sale();
        $payload['payments'] = [
            ['payment_method' => 'cash', 'amount' => 500],
            ['payment_method' => 'card', 'amount' => 600, 'reference' => 'POS 20/10 OP1'],
            ['payment_method' => 'card', 'amount' => 400.50, 'reference' => 'POS 20/10 OP2'],
        ];
        $this->postJson('/api/v1/invoices', $payload)->assertCreated()->assertJsonPath('invoice.status', 'paid')->assertJsonCount(3, 'invoice.payments');
        $payload['submission_key'] = (string) Str::uuid();
        $payload['payments'][1]['reference'] = ' pos 20/10 op1 ';
        $this->postJson('/api/v1/invoices', $payload)->assertUnprocessable();
        $this->assertSame(1, Invoice::count());
        $this->assertEquals(4, $product->fresh()->stock);
    }

    public function test_missing_reference_and_wrong_amount_are_rejected_before_fiscal_emission(): void
    {
        [$user, $product, $payload] = $this->sale();
        $missing = $payload;
        unset($missing['payments'][0]['reference']);
        $this->postJson('/api/v1/invoices', $missing)->assertUnprocessable();
        $wrong = $payload;
        $wrong['payments'][0]['amount'] = 1500.49;
        $this->postJson('/api/v1/invoices', $wrong)->assertUnprocessable();
        $wrong['payments'][0]['amount'] = 1500.501;
        $this->postJson('/api/v1/invoices', $wrong)->assertUnprocessable();
        $this->assertSame(0, Invoice::count());
        $this->assertEquals(5, $product->fresh()->stock);
        // Only this valid attempt reaches ARCA.
        $this->postJson('/api/v1/invoices', $payload)->assertCreated();
    }

    public function test_pending_invoice_can_be_recorded_without_registering_a_device_and_never_twice(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create([
            'user_id' => $user->id, 'invoice_number' => 'PENDING-1',
            'dollar_rate' => 1, 'total_usd' => 1500.50, 'total_local' => 1500.50,
            'status' => 'pending', 'payment_method' => 'card',
        ]);
        $payload = [
            'invoice_id' => $invoice->id, 'amount' => 1500.50, 'transaction_id' => 'Point 20/10 caja 2 op30',
            'confirmed' => true, 'payment_method' => 'card',
        ];
        Http::preventStrayRequests();
        $this->actingAs($user)->postJson('/api/v1/pos-devices/confirm', $payload)->assertOk()->assertJsonPath('invoice.status', 'paid');
        $this->postJson('/api/v1/pos-devices/confirm', $payload)->assertOk();
        $payload['amount'] = 1;
        $this->postJson('/api/v1/pos-devices/confirm', $payload)->assertUnprocessable();
        $this->assertSame(1, $invoice->payments()->count());
        Http::assertNothingSent();
    }

    public function test_existing_point_order_cannot_be_marked_paid_manually(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create([
            'user_id' => $user->id, 'invoice_number' => 'POINT-1',
            'dollar_rate' => 1, 'total_usd' => 1500.50, 'total_local' => 1500.50,
            'status' => 'pending', 'payment_method' => 'card', 'point_order_id' => 'ORDER1',
        ]);
        $this->actingAs($user)->postJson('/api/v1/pos-devices/confirm', [
            'invoice_id' => $invoice->id, 'amount' => 1500.50, 'transaction_id' => 'OP30', 'confirmed' => true,
        ])->assertUnprocessable();
        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertSame(0, $invoice->payments()->count());
    }
}
