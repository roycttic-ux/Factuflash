<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PadronTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_lookup_valid_company_cuit(): void
    {
        $user = User::factory()->create();

        // 33-69345023-9 is a valid CUIT of a legal entity
        $response = $this->actingAs($user)->getJson('/api/v1/padron/33-69345023-9');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'cuit' => '33-69345023-9',
                'iva_condition_id' => 1,
                'status' => 'ACTIVO',
            ]
        ]);
    }

    public function test_authenticated_user_can_lookup_valid_person_cuit(): void
    {
        $user = User::factory()->create();

        // 20-45109702-5 is a valid person CUIT
        $response = $this->actingAs($user)->getJson('/api/v1/padron/20-45109702-5');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'cuit' => '20-45109702-5',
                'iva_condition_id' => 6,
                'status' => 'ACTIVO',
            ]
        ]);
    }

    public function test_invalid_cuit_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/padron/12345678');

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/padron/20-45109702-5');

        $response->assertStatus(401);
    }
}
