<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_view_plans()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/subscription/plans');

        $response->assertStatus(200)
            ->assertJsonStructure(['monthly', 'annual']);
    }

    /** @test */
    public function a_user_can_check_subscription_status()
    {
        $user = User::factory()->create([
            'plan_status' => 'trial',
            'trial_expires_at' => now()->addDays(5)
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/subscription/status');

        $response->assertStatus(200)
            ->assertJson([
                'has_subscription' => false,
                'status' => 'none'
            ]);
    }

    /** @test */
    public function it_calculates_days_left_correctly()
    {
        $user = User::factory()->create([
            'trial_expires_at' => now()->addDays(10)
        ]);

        $this->assertEquals(10, $user->days_left);

        $expiredUser = User::factory()->create([
            'trial_expires_at' => now()->subDays(1)
        ]);

        $this->assertEquals(0, $expiredUser->days_left);
    }

    /** @test */
    public function it_activates_subscription_via_webhook()
    {
        $user = User::factory()->create(['id' => 99, 'plan_status' => 'trial']);
        
        // Mock Mercado Pago response
        Http::fake([
            'api.mercadopago.com/preapproval/*' => Http::response([
                'id' => 'PRE-123',
                'status' => 'authorized',
                'external_reference' => '99-monthly',
                'next_payment_date' => now()->addMonth()->toIso8601String()
            ], 200)
        ]);

        $response = $this->postJson('/api/v1/webhooks/subscription', [
            'type' => 'subscription_preapproval',
            'data' => ['id' => 'PRE-123']
        ]);

        $response->assertStatus(200);
        
        $user->refresh();
        $this->assertEquals('active', $user->plan_status);
        
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'active',
            'mercadopago_id' => 'PRE-123'
        ]);
    }
}
