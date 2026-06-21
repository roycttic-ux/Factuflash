<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\UserSetting;
use App\Services\AfipService;
use Mockery;
use Mockery\MockInterface;

class AfipInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use a closure binding so that makeWith() also returns our mock
        $mock = \Mockery::mock(AfipService::class);
        $mock->shouldReceive('createInvoice')->andReturn([
            'cae' => '12345678901234',
            'vencimiento' => '20261231',
            'numero' => 5
        ]);
        $mock->shouldReceive('createCreditNote')->andReturn([
            'cae' => '98765432109876',
            'vencimiento' => '20261231',
            'numero' => 6
        ]);

        $this->app->bind(AfipService::class, function () use ($mock) {
            return $mock;
        });
    }

    /**
     * Prueba la creación de una factura y su comunicación real con AFIP (Homologación).
     */
    public function test_invoice_creation_with_real_afip()
    {
        $user = User::factory()->create([
            'plan_status' => 'active'
        ]);
        $user->save();

        // Configuración de AFIP real en homologación
        UserSetting::create([
            'user_id' => $user->id,
            'arca_cuit' => '20451097025',
            'arca_cert_path' => 'certs/users/1/certificado.crt',
            'arca_key_path' => 'certs/users/1/privada.key',
            'tax_condition' => 'Monotributista'
        ]);

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Producto de Prueba',
            'cost_usd' => 10, // Un costo realista para no exceder límites en homologación
            'stock' => 10
        ]);

        $payload = [
            'notify_afip' => true,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1
                ]
            ]
        ];

        $response = $this->actingAs($user)
                         ->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        
        $invoice = Invoice::where('user_id', $user->id)->first();
        $this->assertNotNull($invoice->cae, 'El CAE no debe ser nulo tras comunicarse con AFIP');
        $this->assertEquals('paid', $invoice->status);
    }

    /**
     * Prueba la anulación de una factura con Nota de Crédito AFIP real.
     */
    public function test_invoice_annulment_with_real_credit_note()
    {
        $user = User::factory()->create([
            'plan_status' => 'active'
        ]);
        $user->save();

        UserSetting::create([
            'user_id' => $user->id,
            'arca_cuit' => '20451097025',
            'arca_cert_path' => 'certs/users/1/certificado.crt',
            'arca_key_path' => 'certs/users/1/privada.key',
            'tax_condition' => 'Monotributista'
        ]);

        // Para anular una factura, primero necesitamos crear una real en AFIP
        // de lo contrario AFIP nos rechazará la nota de crédito por no encontrar el comprobante original.
        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Producto de Prueba NC',
            'cost_usd' => 10,
            'stock' => 10
        ]);

        $payload = [
            'notify_afip' => true,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1
                ]
            ]
        ];

        // 1. Crear la factura real primero
        $createResponse = $this->actingAs($user)->postJson('/api/v1/invoices', $payload);
        $createResponse->assertStatus(201);
        $invoiceId = $createResponse->json('invoice.id');
        
        $invoice = Invoice::find($invoiceId);
        $this->assertNotNull($invoice->cae); // Asegurar que se creó en AFIP

        // 2. Anular la factura creando la NC real
        $response = $this->actingAs($user)
                         ->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertStatus(200);
        
        $invoice->refresh();
        $this->assertEquals('annulled', $invoice->status);
        $this->assertNotNull($invoice->nc_cae, 'El CAE de la nota de crédito no debe ser nulo tras comunicarse con AFIP');
    }
}
