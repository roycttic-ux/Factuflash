<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PosDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class PosDeviceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_register_a_pos_device()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/pos-devices', [
            'name' => 'Caja Principal',
            'type' => 'mercadopago_point',
            'external_id' => 'SN123456'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('pos_devices', [
            'name' => 'Caja Principal',
            'external_id' => 'SN123456'
        ]);
    }

    /** @test */
    public function a_device_can_confirm_a_payment()
    {
        $user = User::factory()->create(['id' => 1]);
        $device = PosDevice::create([
            'user_id' => $user->id,
            'name' => 'Test POS',
            'type' => 'traditional_pos',
            'external_id' => 'TEST001'
        ]);

        // Simulamos el webhook/bridge avisando un cobro
        $response = $this->actingAs($user)->postJson('/api/v1/pos-devices/confirm', [
            'external_id' => 'TEST001',
            'amount' => 1500.50,
            'transaction_id' => 'TRX_ABC'
        ]);

        $response->assertStatus(200);
        
        // Verificamos que se haya guardado en el cache para el polling del frontend
        $this->assertTrue(Cache::has('pos_confirm_TEST001'));
        $cached = Cache::get('pos_confirm_TEST001');
        $this->assertEquals('approved', $cached['status']);
    }

    /** @test */
    public function polling_endpoint_returns_pending_if_no_payment()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/pos-devices/status/UNKNOWN');

        $response->assertStatus(200)
            ->assertJson(['status' => 'pending']);
    }

    /** @test */
    public function a_user_can_download_dynamic_agent_preconfigured()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/v1/pos-devices/download-agent/macos?token={$user->api_token}");

        $response->assertStatus(200);
        
        if (class_exists('ZipArchive')) {
            $this->assertStringContainsString('application/zip', $response->headers->get('Content-Type'));
        } else {
            $this->assertStringContainsString('text/x-python', $response->headers->get('Content-Type'));
        }
    }
}
