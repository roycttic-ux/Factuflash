<?php

namespace Tests\Feature;

use App\Models\Recipient;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\InvoiceFiscalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerceptionsControlTest extends TestCase
{
    use RefreshDatabase;

    private function setupMerchant(array $settingOverrides = []): array
    {
        $user = User::factory()->create();
        $settings = UserSetting::create(array_merge([
            'user_id' => $user->id,
            'tax_condition' => 'Responsable Inscripto',
            'arca_cuit' => '20451097025',
            'company_name' => 'Empresa Test',
        ], $settingOverrides));

        return [$user, $settings];
    }

    public function test_iibb_reference_settings_do_not_create_automatic_tributes_when_rate_is_missing(): void
    {
        [$user, $settings] = $this->setupMerchant([
            'iibb_regime' => 'agente_percepcion',
            'default_iibb_alicuota' => null,
        ]);

        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente RI Sin Alicuota',
            'document_id' => '33-69345023-9',
            'iva_condition_id' => 1, // Responsable Inscripto
            'iibb_alicuota' => null,
            'iibb_exempt' => false,
        ]);

        $items = [
            ['description_snapshot' => 'Producto 1', 'unit_price_usd' => 121, 'quantity' => 1, 'iva_id' => 5], // 100 net + 21 vat
        ];

        $service = app(InvoiceFiscalService::class);

        $res = $service->calculate($settings, $recipient, $items);

        $this->assertSame(0.0, (float) $res['tributos_total']);
        $this->assertSame(121.0, (float) $res['total_local']);
        $this->assertEmpty($res['fiscal']['tributos']);
    }

    public function test_business_and_recipient_rates_are_reference_only_without_manual_tribute(): void
    {
        [$user, $settings] = $this->setupMerchant([
            'iibb_regime' => 'agente_percepcion',
            'default_iibb_alicuota' => 1.5,
        ]);

        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente RI con 3%',
            'document_id' => '33-69345023-9',
            'iva_condition_id' => 1,
            'iibb_alicuota' => 3.0,
            'iibb_exempt' => false,
        ]);

        $items = [
            ['description_snapshot' => 'Producto 1', 'unit_price_usd' => 121, 'quantity' => 1, 'iva_id' => 5], // net 100, vat 21
        ];

        $service = app(InvoiceFiscalService::class);
        $res = $service->calculate($settings, $recipient, $items);

        $this->assertSame(100.0, (float) $res['fiscal']['net']);
        $this->assertSame(21.0, (float) $res['fiscal']['vat']);
        $this->assertSame(0.0, (float) $res['tributos_total']);
        $this->assertSame(121.0, (float) $res['total_local']);
        $this->assertEmpty($res['fiscal']['tributos']);
    }

    public function test_perception_agent_respects_exempt_recipient(): void
    {
        [$user, $settings] = $this->setupMerchant([
            'iibb_regime' => 'agente_percepcion',
            'default_iibb_alicuota' => 3.0,
        ]);

        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente RI Exento de IIBB',
            'document_id' => '33-69345023-9',
            'iva_condition_id' => 1,
            'iibb_exempt' => true,
        ]);

        $items = [
            ['description_snapshot' => 'Producto 1', 'unit_price_usd' => 121, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);
        $res = $service->calculate($settings, $recipient, $items);

        $this->assertSame(0.0, (float) $res['tributos_total']);
        $this->assertSame(121.0, (float) $res['total_local']);
        $this->assertEmpty($res['fiscal']['tributos']);
    }

    public function test_explicit_tributos_validated_and_calculated_accurately(): void
    {
        [$user, $settings] = $this->setupMerchant();

        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente RI',
            'document_id' => '33-69345023-9',
            'iva_condition_id' => 1,
        ]);

        $items = [
            ['description_snapshot' => 'Producto 1', 'unit_price_usd' => 121, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);

        $explicit = [
            ['id' => 1, 'desc' => 'Percepción IVA RG 5329', 'jurisdiction' => 'Nacional', 'base_imp' => 100, 'alicuota' => 3.0, 'importe' => 3.0],
            ['id' => 2, 'desc' => 'Percepción IIBB CABA', 'jurisdiction' => 'Ciudad Autónoma de Buenos Aires', 'base_imp' => 100, 'alicuota' => 2.0, 'importe' => 2.0],
        ];

        $res = $service->calculate($settings, $recipient, $items, $explicit);

        $this->assertSame(5.0, (float) $res['tributos_total']);
        $this->assertSame(126.0, (float) $res['total_local']);
        $this->assertCount(2, $res['fiscal']['tributos']);
    }

    public function test_explicit_tribute_keeps_the_manually_entered_amount(): void
    {
        [$user, $settings] = $this->setupMerchant();

        $recipient = Recipient::create([
            'user_id' => $user->id,
            'name' => 'Cliente RI',
            'document_id' => '33-69345023-9',
            'iva_condition_id' => 1,
        ]);

        $items = [
            ['description_snapshot' => 'Producto 1', 'unit_price_usd' => 121, 'quantity' => 1, 'iva_id' => 5],
        ];

        $service = app(InvoiceFiscalService::class);

        $explicit = [
            ['id' => 2, 'desc' => 'Percepción IIBB', 'jurisdiction' => 'Buenos Aires', 'base_imp' => 100, 'alicuota' => 3.0, 'importe' => 50.0], // mismatch: 3% of 100 is 3, not 50
        ];

        $res = $service->calculate($settings, $recipient, $items, $explicit);

        $this->assertSame(50.0, (float) $res['tributos_total']);
        $this->assertSame(171.0, (float) $res['total_local']);
        $this->assertSame(50.0, (float) $res['fiscal']['tributos'][0]['importe']);
    }
}
