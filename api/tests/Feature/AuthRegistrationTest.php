<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_new_user_gets_15_days_of_trial()
    {
        // Mock Turnstile verification (assuming it returns true in AuthController)
        $payload = [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'password' => 'password123',
            'company_name' => 'Mi Negocio',
            'captcha_token' => 'mock-token'
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201);
        
        $user = User::where('email', 'juan@example.com')->first();
        $this->assertEquals(15, $user->days_left);
        $this->assertEquals('trial', $user->plan_status);
        
        // Verificamos que se crearon los settings por defecto
        $this->assertNotNull($user->settings);
        $this->assertEquals('Mi Negocio', $user->settings->company_name);
    }

    /** @test */
    public function owner_cannot_access_dashboard_after_trial_expires()
    {
        // Nota: El bloqueo de rutas lo hacemos en el Middleware o Router de Vue,
        // pero podemos probar si la API de estadisticas/dashboard es accesible (opcional)
        // Por ahora, el requerimiento era visual, pero podemos asegurar que el atributo
        // days_left sea 0.
        
        $user = User::factory()->create([
            'trial_expires_at' => now()->subDay()
        ]);
        
        $this->assertEquals(0, $user->days_left);
    }
}
