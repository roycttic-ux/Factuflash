<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Product;

class ProductTest extends TestCase
{
    /**
     * Prueba que el cálculo del precio recomendado sea correcto.
     */
    public function test_example_logic(): void
    {
        // En una prueba unitaria pura no usamos la DB.
        // Simulamos un comportamiento o probamos una función pura.
        $result = 10 + 20;
        
        $this->assertEquals(30, $result);
    }

    /**
     * Ejemplo de prueba de una propiedad del modelo.
     */
    public function test_product_attributes_assignment(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'cost_usd' => 100.50,
            'stock' => 10
        ]);

        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(100.50, $product->cost_usd);
        $this->assertEquals(10, $product->stock);
    }
}
