<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_is_redirected_to_role_panel(): void
    {
        User::factory()->create([
            'usuario' => 'contador1',
            'password' => Hash::make('secreto123'),
            'rol' => 'Contador',
        ]);

        $response = $this->post(route('login.store'), [
            'usuario' => 'contador1',
            'password' => 'secreto123',
        ]);

        $response->assertRedirect(route('panel.contador'));
        $this->assertAuthenticated();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'usuario' => 'inactivo',
            'password' => Hash::make('secreto123'),
            'activo' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'usuario' => 'inactivo',
            'password' => 'secreto123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('usuario');
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
