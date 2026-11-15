<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile.secret' => 'test-secret']);
    }

    public function test_high_volume_registration_is_referred_to_whatsapp_without_creating_account(): void
    {
        Http::preventStrayRequests();
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Comercio', 'email' => 'volume@example.com', 'password' => 'password123',
            'captcha_token' => 'mock-token', 'expected_monthly_invoices' => 1001, 'simultaneous_registers' => 1,
        ])->assertStatus(422)->assertJsonPath('contact_url', 'https://wa.me/5492954326436');
        $this->assertDatabaseMissing('users', ['email' => 'volume@example.com']);
        Http::assertNothingSent();
    }

    public function test_one_thousand_invoices_and_three_registers_still_gets_standard_trial(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Comercio', 'email' => 'standard@example.com', 'password' => 'password123',
            'captcha_token' => 'mock-token', 'expected_monthly_invoices' => 1000, 'simultaneous_registers' => 3,
        ])->assertCreated()->assertJsonPath('user.expected_monthly_invoices', 1000)->assertJsonPath('user.simultaneous_registers', 3)->assertJsonPath('user.days_left', 7);
    }

    public function test_four_registers_is_referred_even_with_low_invoice_volume(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Comercio', 'email' => 'registers@example.com', 'password' => 'password123',
            'captcha_token' => 'mock-token', 'expected_monthly_invoices' => 100, 'simultaneous_registers' => 4,
        ])->assertStatus(422)->assertJsonPath('contact_url', 'https://wa.me/5492954326436');
        $this->assertDatabaseMissing('users', ['email' => 'registers@example.com']);
    }

    public function test_new_user_gets_exactly_seven_trial_days_and_cannot_set_paid_status(): void
    {
        $this->travelTo(now()->startOfSecond());
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Juan Perez', 'email' => 'juan@example.com', 'password' => 'password123',
            'company_name' => 'Mi Negocio', 'captcha_token' => 'mock-token',
            'plan_status' => 'active', 'paid_until' => now()->addYear()->toIso8601String(),
        ])->assertCreated();
        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertEquals(7, $user->days_left);
        $this->assertTrue(now()->addDays(7)->equalTo($user->trial_expires_at));
        $this->assertEquals('trial', $user->plan_status);
        $this->assertNull($user->paid_until);
        $this->assertEquals('Mi Negocio', $user->settings->company_name);
    }
}
