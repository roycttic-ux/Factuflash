<?php

namespace Tests\Feature;

use App\Models\{User, UserSetting, Product, Recipient, Invoice};
use App\Services\AfipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalCreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private function setupSale(int $condition = 1): array
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'tax_condition' => 'Responsable Inscripto', 'arca_cuit' => '20451097025', 'arca_cert_path' => 'mock', 'arca_key_path' => 'mock']);
        $product = Product::factory()->create(['user_id' => $user->id, 'cost_usd' => 121, 'iva_id' => 5, 'stock' => 5, 'unlimited_stock' => false]);
        $recipient = Recipient::create(['user_id' => $user->id, 'name' => 'Cliente', 'document_id' => '20123456786', 'iva_condition_id' => $condition]);
        $mock = \Mockery::mock(AfipService::class);
        $this->app->bind(AfipService::class, fn () => $mock);
        $this->actingAs($user);
        return [$product, $recipient, $mock, ['recipient_id' => $recipient->id, 'submission_key' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]]]];
    }

    public function test_a_and_credit_note_preserve_original_vat_and_restore_stock_only_once(): void
    {
        [$product, $recipient, $mock, $payload] = $this->setupSale();
        $mock->shouldReceive('createInvoice')->once()->withArgs(fn ($d) => $d['cbte_tipo'] === 1 && $d['fiscal']['net'] == 100 && $d['fiscal']['vat'] == 21)
            ->andReturn(['cae' => '12345678901234', 'vencimiento' => '20261231', 'numero' => 15]);
        $invoice = $this->postJson('/api/v1/invoices', $payload)->assertCreated()->json('invoice');
        $product->update(['iva_id' => 4, 'cost_usd' => 500]);
        $recipient->update(['iva_condition_id' => 5, 'document_id' => '12345678']);
        $mock->shouldReceive('createCreditNote')->once()->withArgs(fn ($d) =>
            $d['cbte_tipo'] === 3 && $d['orig_cbte_tipo'] === 1 && $d['orig_cbte_nro'] === 15
            && $d['fiscal']['vat'] == 21 && $d['condicion_iva_receptor'] === 1 && $d['doc_nro'] === 20123456786)
            ->andReturn(['cae' => '98765432109876', 'vencimiento' => '20261231', 'numero' => 7]);
        $this->deleteJson('/api/v1/invoices/'.$invoice['id'])->assertOk();
        $this->deleteJson('/api/v1/invoices/'.$invoice['id'])->assertStatus(422);
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'annulled', 'nc_cae' => '98765432109876', 'nc_number' => 7]);
    }

    public function test_b_for_consumer_with_cuit_and_failed_credit_preserves_stock_and_invoice(): void
    {
        [$product, , $mock, $payload] = $this->setupSale(5);
        $mock->shouldReceive('createInvoice')->once()->withArgs(fn ($d) => $d['cbte_tipo'] === 6 && $d['condicion_iva_receptor'] === 5)
            ->andReturn(['cae' => '12345678901234', 'vencimiento' => '20261231', 'numero' => 2]);
        $id = $this->postJson('/api/v1/invoices', $payload)->assertCreated()->json('invoice.id');
        $mock->shouldReceive('createCreditNote')->once()->withArgs(fn ($d) => $d['cbte_tipo'] === 8)->andThrow(new \Exception('ARCA no disponible'));
        $this->deleteJson('/api/v1/invoices/'.$id)->assertStatus(422);
        $this->assertEquals(4, $product->fresh()->stock);
        $this->assertEquals('paid', Invoice::find($id)->status);
        $this->assertNull(Invoice::find($id)->nc_cae);
    }

    public function test_unconfigured_product_vat_cannot_issue_or_deduct_stock(): void
    {
        [$product, , $mock, $payload] = $this->setupSale();
        $product->update(['iva_id' => null]);
        $mock->shouldNotReceive('createInvoice');
        $this->postJson('/api/v1/invoices', $payload)->assertStatus(422);
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertEquals(0, Invoice::count());
    }
}
