<?php

namespace Tests\Feature;

use App\Models\{User, UserSetting, Product, Recipient, Invoice};
use App\Services\{AfipService, InvoiceFiscalService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Bloque 2 — Reglas de comprobantes A, B, y C.
 */
class ComprobanteTypeRulesTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // 1. cbtePrefix
    // ─────────────────────────────────────────────────────────────────────────

    public function test_cbte_prefix_maps_correctly(): void
    {
        $this->assertSame('A', InvoiceFiscalService::cbtePrefix(1));
        $this->assertSame('A', InvoiceFiscalService::cbtePrefix(3));
        $this->assertSame('B', InvoiceFiscalService::cbtePrefix(6));
        $this->assertSame('B', InvoiceFiscalService::cbtePrefix(8));
        $this->assertSame('C', InvoiceFiscalService::cbtePrefix(11));
        $this->assertSame('C', InvoiceFiscalService::cbtePrefix(13));
    }

    public function test_cbte_prefix_throws_for_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InvoiceFiscalService::cbtePrefix(99);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. creditNoteType
    // ─────────────────────────────────────────────────────────────────────────

    public function test_credit_note_type_maps_correctly(): void
    {
        $this->assertSame(3,  InvoiceFiscalService::creditNoteType(1));
        $this->assertSame(8,  InvoiceFiscalService::creditNoteType(6));
        $this->assertSame(13, InvoiceFiscalService::creditNoteType(11));
    }

    public function test_credit_note_type_throws_for_nc_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nota de Cr');
        InvoiceFiscalService::creditNoteType(8);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. assertTypeMatchesCondition
    // ─────────────────────────────────────────────────────────────────────────

    public function test_factura_a_accepted_for_ri_and_monotributista(): void
    {
        InvoiceFiscalService::assertTypeMatchesCondition(1, 1);
        InvoiceFiscalService::assertTypeMatchesCondition(1, 6);
        InvoiceFiscalService::assertTypeMatchesCondition(3, 1);
        InvoiceFiscalService::assertTypeMatchesCondition(3, 6);
        $this->addToAssertionCount(4);
    }

    public function test_factura_a_rejected_for_consumidor_final(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Factura A');
        InvoiceFiscalService::assertTypeMatchesCondition(1, 5);
    }

    public function test_factura_a_rejected_for_exento(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Factura A');
        InvoiceFiscalService::assertTypeMatchesCondition(1, 4);
    }

    public function test_factura_b_accepted_for_cf_and_exento(): void
    {
        InvoiceFiscalService::assertTypeMatchesCondition(6, 5);
        InvoiceFiscalService::assertTypeMatchesCondition(6, 4);
        InvoiceFiscalService::assertTypeMatchesCondition(8, 5);
        InvoiceFiscalService::assertTypeMatchesCondition(8, 4);
        $this->addToAssertionCount(4);
    }

    public function test_factura_b_rejected_for_responsable_inscripto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Factura B');
        InvoiceFiscalService::assertTypeMatchesCondition(6, 1);
    }

    public function test_factura_b_rejected_for_monotributista(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Factura B');
        InvoiceFiscalService::assertTypeMatchesCondition(6, 6);
    }

    public function test_factura_c_accepted_for_any_condition(): void
    {
        foreach ([1, 4, 5, 6] as $cond) {
            InvoiceFiscalService::assertTypeMatchesCondition(11, $cond);
            InvoiceFiscalService::assertTypeMatchesCondition(13, $cond);
        }
        $this->addToAssertionCount(8);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. InvoiceFiscalService — cbte_tipo per issuer x receiver
    // ─────────────────────────────────────────────────────────────────────────

    private function makeSettings(string $taxCondition = 'Responsable Inscripto'): UserSetting
    {
        $user = User::factory()->create();
        return UserSetting::create([
            'user_id'       => $user->id,
            'tax_condition' => $taxCondition,
            'arca_cuit'     => '20451097025',
            'company_name'  => 'Empresa Test',
        ]);
    }

    private function makeRecipient(UserSetting $s, int $cond, string $doc = '20123456786'): Recipient
    {
        return Recipient::create(['user_id' => $s->user_id, 'name' => 'Cte', 'document_id' => $doc, 'iva_condition_id' => $cond]);
    }

    private function makeItems(int $ivaId = 5): array
    {
        return [['description_snapshot' => 'Item', 'unit_price_usd' => 100, 'quantity' => 1, 'iva_id' => $ivaId]];
    }

    public function test_ri_to_ri_generates_factura_a_with_iva(): void
    {
        $s = $this->makeSettings();
        $r = $this->makeRecipient($s, 1, '33693450239');
        $items = $this->makeItems(5);
        $snap = app(InvoiceFiscalService::class)->calculate($s, $r, $items);
        $this->assertSame(1, $snap['cbte_tipo']);
        $this->assertEqualsWithDelta(82.64, $snap['fiscal']['net'], 0.01);
        $this->assertEqualsWithDelta(17.36, $snap['fiscal']['vat'], 0.01);
        $this->assertCount(1, $snap['fiscal']['rates']);
        $this->assertNull($snap['rg5003_legend']);
    }

    public function test_ri_to_monotributista_generates_factura_a_with_rg5003_and_iva(): void
    {
        $s = $this->makeSettings();
        $r = $this->makeRecipient($s, 6);
        $items = $this->makeItems(5);
        $snap = app(InvoiceFiscalService::class)->calculate($s, $r, $items);
        $this->assertSame(1, $snap['cbte_tipo']);
        $this->assertTrue($snap['is_monotributo_receiver']);
        $this->assertNotNull($snap['rg5003_legend']);
        $this->assertGreaterThan(0, $snap['fiscal']['vat']);
        $this->assertCount(1, $snap['fiscal']['rates']);
    }

    public function test_ri_to_exento_generates_factura_b_with_product_vat(): void
    {
        $s = $this->makeSettings();
        $r = $this->makeRecipient($s, 4, '30546666553');
        $items = $this->makeItems(5);
        $snap = app(InvoiceFiscalService::class)->calculate($s, $r, $items);
        $this->assertSame(6, $snap['cbte_tipo']);
        $this->assertSame(4, $snap['condicion_iva_receptor']);
        $this->assertSame(80, $snap['doc_tipo']);
        $this->assertEqualsWithDelta(82.64, $snap['fiscal']['net'], 0.01);
        $this->assertEqualsWithDelta(17.36, $snap['fiscal']['vat'], 0.01);
        $this->assertCount(1, $snap['fiscal']['rates']);
    }

    public function test_ri_to_exento_without_cuit_throws(): void
    {
        $s = $this->makeSettings();
        $r = Recipient::create(['user_id' => $s->user_id, 'name' => 'Exento', 'iva_condition_id' => 4]);
        $items = $this->makeItems(5);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CUIT');
        app(InvoiceFiscalService::class)->calculate($s, $r, $items);
    }

    public function test_ri_to_anonymous_cf_generates_factura_b(): void
    {
        $s = $this->makeSettings();
        $items = $this->makeItems(5);
        $snap = app(InvoiceFiscalService::class)->calculate($s, null, $items);
        $this->assertSame(6, $snap['cbte_tipo']);
        $this->assertSame(99, $snap['doc_tipo']);
        $this->assertSame(5, $snap['condicion_iva_receptor']);
        $this->assertEqualsWithDelta(17.36, $snap['fiscal']['vat'], 0.01);
    }

    public function test_monotributista_generates_factura_c(): void
    {
        $s = $this->makeSettings('Monotributista');
        $items = $this->makeItems(5);
        $snap = app(InvoiceFiscalService::class)->calculate($s, null, $items);
        $this->assertSame(11, $snap['cbte_tipo']);
        $this->assertEquals(0, $snap['fiscal']['vat']);
        $this->assertEmpty($snap['fiscal']['rates']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Full endpoint — Factura A to Monotributista
    // ─────────────────────────────────────────────────────────────────────────

    public function test_endpoint_factura_a_to_monotributista_stores_rg5003(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'tax_condition' => 'Responsable Inscripto',
            'arca_cuit' => '20451097025', 'arca_cert_path' => 'mock', 'arca_key_path' => 'mock']);
        $product = Product::factory()->create(['user_id' => $user->id, 'cost_usd' => 100, 'iva_id' => 5, 'stock' => 5]);
        $recipient = Recipient::create(['user_id' => $user->id, 'name' => 'Mono SA',
            'document_id' => '20123456786', 'iva_condition_id' => 6]);

        $mock = \Mockery::mock(AfipService::class);
        $mock->shouldReceive('createInvoice')->once()
            ->withArgs(fn ($d) => $d['cbte_tipo'] === 1 && $d['condicion_iva_receptor'] === 6 && count($d['fiscal']['rates']) > 0)
            ->andReturn(['cae' => '12345678901234', 'vencimiento' => '20261231', 'numero' => 1]);
        $this->app->bind(AfipService::class, fn () => $mock);

        $this->actingAs($user)->postJson('/api/v1/invoices', [
            'recipient_id' => $recipient->id, 'submission_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $invoice = Invoice::where('user_id', $user->id)->first();
        $this->assertSame(1, $invoice->cbte_tipo);
        $this->assertStringStartsWith('A-', $invoice->invoice_number);
        $this->assertNotNull($invoice->fiscal_snapshot['rg5003_legend']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Full endpoint — Factura B to Exento
    // ─────────────────────────────────────────────────────────────────────────

    public function test_endpoint_factura_b_to_exento_has_b_prefix(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'tax_condition' => 'Responsable Inscripto',
            'arca_cuit' => '20451097025', 'arca_cert_path' => 'mock', 'arca_key_path' => 'mock']);
        $product = Product::factory()->create(['user_id' => $user->id, 'cost_usd' => 200, 'iva_id' => 5, 'stock' => 5]);
        $recipient = Recipient::create(['user_id' => $user->id, 'name' => 'Exenta SA',
            'document_id' => '30546666553', 'iva_condition_id' => 4]);

        $mock = \Mockery::mock(AfipService::class);
        $mock->shouldReceive('createInvoice')->once()
            ->withArgs(fn ($d) => $d['cbte_tipo'] === 6 && $d['condicion_iva_receptor'] === 4 && $d['doc_tipo'] === 80 && $d['fiscal']['vat'] == 34.71 && $d['fiscal']['net'] == 165.29)
            ->andReturn(['cae' => '12345678901234', 'vencimiento' => '20261231', 'numero' => 2]);
        $this->app->bind(AfipService::class, fn () => $mock);

        $this->actingAs($user)->postJson('/api/v1/invoices', [
            'recipient_id' => $recipient->id, 'submission_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $invoice = Invoice::where('user_id', $user->id)->first();
        $this->assertSame(6, $invoice->cbte_tipo);
        $this->assertStringStartsWith('B-', $invoice->invoice_number);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. NC type follows invoice type via creditNoteType()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_credit_note_for_factura_a_generates_nc_a(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'tax_condition' => 'Responsable Inscripto',
            'arca_cuit' => '20451097025', 'arca_cert_path' => 'mock', 'arca_key_path' => 'mock']);
        $product = Product::factory()->create(['user_id' => $user->id, 'cost_usd' => 121, 'iva_id' => 5, 'stock' => 3]);
        $recipient = Recipient::create(['user_id' => $user->id, 'name' => 'RI Test',
            'document_id' => '33693450239', 'iva_condition_id' => 1]);

        $mock = \Mockery::mock(AfipService::class);
        $mock->shouldReceive('createInvoice')->once()
            ->withArgs(fn ($d) => $d['cbte_tipo'] === 1)
            ->andReturn(['cae' => '12345678901234', 'vencimiento' => '20261231', 'numero' => 10]);
        $mock->shouldReceive('createCreditNote')->once()
            ->withArgs(fn ($d) => $d['cbte_tipo'] === 3 && $d['orig_cbte_tipo'] === 1 && $d['orig_cbte_nro'] === 10)
            ->andReturn(['cae' => '98765432109876', 'vencimiento' => '20261231', 'numero' => 1]);
        $this->app->bind(AfipService::class, fn () => $mock);
        $this->actingAs($user);

        $invoice = $this->postJson('/api/v1/invoices', [
            'recipient_id' => $recipient->id, 'submission_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('invoice');

        $this->deleteJson('/api/v1/invoices/' . $invoice['id'])->assertOk();
        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'annulled', 'nc_cae' => '98765432109876']);
    }
}
