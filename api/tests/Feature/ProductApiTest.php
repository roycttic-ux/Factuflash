<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;

class ProductApiTest extends TestCase
{
    use RefreshDatabase; // Esto limpia la base de datos de pruebas en cada ejecución

    /**
     * Prueba que un usuario autenticado puede listar sus productos.
     */
    public function test_user_can_list_products(): void
    {
        // 1. Crear un usuario
        $user = User::factory()->create();

        // 2. Crear algunos productos para ese usuario
        Product::create([
            'user_id' => $user->id,
            'name' => 'Producto 1',
            'cost_usd' => 10,
            'stock' => 5
        ]);

        // 3. Actuar como el usuario y llamar a la API
        $response = $this->actingAs($user)
                         ->getJson('/api/v1/products');

        // 4. Verificar el resultado
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'products');
    }

    /**
     * Prueba que no se puede acceder a los productos sin estar autenticado.
     */
    public function test_unauthenticated_user_cannot_list_products(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401);
    }
}
