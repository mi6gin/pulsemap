<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_create_authenticated_session(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'demo@example.com');
        $this->assertAuthenticatedAs($user);
    }

    public function test_returns_422_for_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'demo@example.com']);

        $response = $this->postJson('/login', [
            'email' => 'demo@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Неверный email или пароль.');
        $this->assertGuest();
    }

    public function test_current_host_with_custom_port_is_treated_as_stateful(): void
    {
        $request = Request::create('/api/me', 'GET', server: [
            'HTTP_HOST' => '127.0.0.1:8018',
            'HTTP_REFERER' => 'http://127.0.0.1:8018/',
        ]);

        $this->assertTrue(EnsureFrontendRequestsAreStateful::fromFrontend($request));
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/logout');

        $response->assertOk()->assertJsonPath('message', 'Сессия завершена.');
        $this->assertGuest();
    }
}
