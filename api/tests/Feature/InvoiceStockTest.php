<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Services\AfipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceStockTest extends TestCase
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

        $this->app->bind(AfipService::class, function () use ($mock) {
            return $mock;
        });
    }

    private function configureAfip($user)
    {
        \App\Models\UserSetting::create([
            'user_id' => $user->id,
            'arca_cuit' => '20451097025',
            'arca_cert_path' => 'certs/users/1/certificado.crt',
            'arca_key_path' => 'certs/users/1/privada.key',
            'tax_condition' => 'Monotributista'
        ]);
    }

    /** @test */
    public function it_deducts_stock_correctly_for_simple_products()
    {
        $user = User::factory()->create();
        $this->configureAfip($user);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'stock' => 10,
            'unlimited_stock' => false,
            'cost_usd' => 100
        ]);

        $payload = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3]
            ],
            'payment_method' => 'cash'
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/invoices', $payload);
        $response->assertStatus(201);
        $this->assertEquals(7, $product->refresh()->stock);
    }

    /** @test */
    public function it_deducts_stock_correctly_for_variants()
    {
        $user = User::factory()->create();
        $this->configureAfip($user);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'variants' => [
                ['name' => 'Talle S', 'stock' => 5],
                ['name' => 'Talle L', 'stock' => 10]
            ],
            'unlimited_stock' => false,
            'cost_usd' => 100
        ]);

        $payload = [
            'items' => [
                [
                    'product_id' => $product->id, 
                    'quantity' => 2,
                    'variant_index' => 1 // Vendemos 2 del Talle L
                ]
            ],
            'payment_method' => 'cash'
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        
        $updatedProduct = $product->refresh();
        $this->assertEquals(8, $updatedProduct->variants[1]['stock']); // 10 - 2 = 8
        $this->assertEquals(5, $updatedProduct->variants[0]['stock']); // Sigue igual
    }

    /** @test */
    public function it_does_not_deduct_stock_for_unlimited_items()
    {
        $user = User::factory()->create();
        $this->configureAfip($user);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'stock' => 10,
            'unlimited_stock' => true,
            'cost_usd' => 100
        ]);

        $payload = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5]
            ],
            'payment_method' => 'cash'
        ];

        $this->actingAs($user)->postJson('/api/v1/invoices', $payload);

        $this->assertEquals(10, $product->refresh()->stock); // No debe cambiar
    }
}
