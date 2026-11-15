<?php

namespace Tests\Feature;

use App\Models\Recipient;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\InvoiceFiscalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FiscalValidationRulesTest extends TestCase
{
    use RefreshDatabase;
    private function setupMerchant(string $taxCondition = 'Responsable Inscripto'): array
    {
        $user = User::factory()->create();
        $settings = UserSetting::create([
            'user_id' => $user->id,
            'tax_condition' => $taxCondition,
            'arca_cuit' => '20451097025',
            'company_name' => 'Empresa Test',
        ]);

        return [$user, $settings];
    }

    public function test_factura_a_to_monotributo_includes_rg5003_legend(): void
    {
        [$user, $settings] = $this->setupMerchant('Responsable Inscripto');
        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente Monotributista',
            'document_id' => '20-12345678-6',
            'iva_condition_id' => 6, // Monotributista
        ]);

        $items = [
            ['description_snapshot' => 'Item 1', 'unit_price_usd' => 100, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);
        $snapshot = $service->calculate($settings, $recipient, $items);

        $this->assertSame(1, $snapshot['cbte_tipo'], 'Debe ser Factura A');
        $this->assertTrue($snapshot['is_monotributo_receiver']);
        $this->assertNotNull($snapshot['rg5003_legend']);
        $this->assertStringContainsString('Régimen de Sostenimiento e Inclusión Fiscal', $snapshot['rg5003_legend']);
    }

    public function test_factura_a_to_responsable_inscripto_does_not_include_rg5003_legend(): void
    {
        [$user, $settings] = $this->setupMerchant('Responsable Inscripto');
        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente RI',
            'document_id' => '33-69345023-9',
            'iva_condition_id' => 1, // Responsable Inscripto
        ]);

        $items = [
            ['description_snapshot' => 'Item 1', 'unit_price_usd' => 100, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);
        $snapshot = $service->calculate($settings, $recipient, $items);

        $this->assertSame(1, $snapshot['cbte_tipo']);
        $this->assertFalse($snapshot['is_monotributo_receiver']);
        $this->assertNull($snapshot['rg5003_legend']);
    }

    public function test_anonymous_consumer_final_above_limit_is_rejected(): void
    {
        [$user, $settings] = $this->setupMerchant('Monotributista');
        
        // No recipient (Consumidor Final anónimo)
        $items = [
            ['description_snapshot' => 'Notebook', 'unit_price_usd' => 350000, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('deben identificar obligatoriamente al comprador con DNI o CUIT');

        $service->calculate($settings, null, $items);
    }

    public function test_anonymous_consumer_final_below_limit_is_allowed(): void
    {
        [$user, $settings] = $this->setupMerchant('Monotributista');
        
        $items = [
            ['description_snapshot' => 'Mouse', 'unit_price_usd' => 15000, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);
        $snapshot = $service->calculate($settings, null, $items);

        $this->assertSame(11, $snapshot['cbte_tipo']);
        $this->assertSame(99, $snapshot['doc_tipo']);
        $this->assertSame(0, $snapshot['doc_nro']);
    }

    public function test_identified_consumer_final_above_limit_is_allowed(): void
    {
        [$user, $settings] = $this->setupMerchant('Monotributista');
        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Juan Perez',
            'document_id' => '35123456', // DNI 8 digits
            'iva_condition_id' => 5, // Consumidor Final
        ]);

        $items = [
            ['description_snapshot' => 'Heladera', 'unit_price_usd' => 500000, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);
        $snapshot = $service->calculate($settings, $recipient, $items);

        $this->assertSame(11, $snapshot['cbte_tipo']);
        $this->assertSame(96, $snapshot['doc_tipo']);
        $this->assertSame(35123456, $snapshot['doc_nro']);
    }

    public function test_invalid_cuit_throws_exception_in_fiscal_service(): void
    {
        [$user, $settings] = $this->setupMerchant('Responsable Inscripto');
        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente Cuit Invalido',
            'document_id' => '20123456789', // Invalid check digit (should be 6)
            'iva_condition_id' => 6,
        ]);

        $items = [
            ['description_snapshot' => 'Item', 'unit_price_usd' => 100, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no es válido');

        $service->calculate($settings, $recipient, $items);
    }

    public function test_recipient_controller_rejects_invalid_cuit(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/customers', [
            'name' => 'Empresa Falsa',
            'document_id' => '20-12345678-0', // Invalid
            'iva_condition_id' => 1, // RI requires valid CUIT
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('document_id');
    }

    public function test_recipient_controller_accepts_valid_cuit(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/customers', [
            'name' => 'Empresa Real',
            'document_id' => '20123456786', // Valid CUIT
            'iva_condition_id' => 1,
        ]);

        $response->assertStatus(201);
        $this->assertSame('20-12345678-6', $response->json('recipient.document_id'));
    }
}
