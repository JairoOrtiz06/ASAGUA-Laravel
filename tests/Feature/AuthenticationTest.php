<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivation_revokes_the_session_on_the_next_request(): void
    {
        $user = User::factory()->create(['rol' => 'Contador', 'password' => Hash::make('secreto123')]);
        $credentials = ['usuario' => $user->usuario, 'password' => 'secreto123'];
        $this->post(route('login.store'), $credentials)->assertRedirect(route('portal.contador'));
        $session = $this->app['session.store'];
        $id = $session->getId();
        $token = $session->token();

        User::whereKey($user->getKey())->update(['activo' => false]);

        $this->get(route('documentos.download', 999999))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('usuario');
        $this->assertGuest();
        $this->assertNotSame($id, $session->getId());
        $this->assertNotSame($token, $session->token());
        $this->get(route('login'))->assertOk();

        User::whereKey($user->getKey())->update(['activo' => true]);
        Auth::forgetGuards();
        $this->get(route('panel.contador'))->assertRedirect(route('login'));
        $this->post(route('login.store'), $credentials)->assertRedirect(route('portal.contador'));
        $this->get(route('panel.contador'))->assertOk();
    }

    public function test_inactive_users_cannot_reach_accounting_write_or_economics_routes(): void
    {
        $user = User::factory()->create(['rol' => 'Contador']);
        foreach ([
            ['GET', 'panel.contador'],
            ['GET', 'ingenieria-economica.index'],
            ['POST', 'partidas.store'],
            ['POST', 'ingenieria-economica.proyectos.store'],
            ['GET', 'reportes.libro-diario.descargar'],
        ] as [$method, $name]) {
            User::whereKey($user->getKey())->update(['activo' => true]);
            $this->actingAs($user);
            User::whereKey($user->getKey())->update(['activo' => false]);
            $this->call($method, route($name))->assertRedirect(route('login'))->assertSessionHasErrors('usuario');
            $this->assertGuest();
        }
    }

    public function test_active_check_is_applied_to_every_authenticated_route(): void
    {
        $protected = 0;
        foreach ($this->app['router']->getRoutes() as $route) {
            if (in_array('auth', $route->gatherMiddleware(), true)) {
                $protected++;
                $this->assertContains(EnsureUserIsActive::class, $this->app['router']->gatherRouteMiddleware($route), $route->uri());
            }
        }
        $this->assertGreaterThan(0, $protected);
    }

    public function test_login_throttle_normalizes_identifiers_and_expires(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['usuario' => 'contador', 'password' => Hash::make('secreto123')]);
        $generic = 'No se pudo iniciar sesión con las credenciales proporcionadas.';
        foreach (['contador', 'CONTADOR', ' contador ', 'Contador', 'contador'] as $identifier) {
            $this->loginAttempt($identifier)->assertSessionHasErrors(['usuario' => $generic]);
        }
        $this->loginAttempt($user->usuario, 'secreto123')
            ->assertSessionHasErrors(['usuario' => 'Demasiados intentos de inicio de sesión. Vuelve a intentarlo en 60 s.']);
        $this->assertGuest();
        $this->loginAttempt('cuenta-inexistente')->assertSessionHasErrors(['usuario' => $generic]);
        $this->travel(61)->seconds();
        $this->loginAttempt($user->usuario, 'secreto123')->assertRedirect(route('portal.contador'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secreto123')]);
        for ($i = 0; $i < 4; $i++) {
            $this->loginAttempt($user->usuario)->assertSessionHasErrors('usuario');
        }
        $this->loginAttempt($user->usuario, 'secreto123')->assertRedirect(route('portal.contador'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->loginAttempt($user->usuario)->assertSessionHasErrors([
            'usuario' => 'No se pudo iniciar sesión con las credenciales proporcionadas.',
        ]);
        $this->loginAttempt($user->usuario, 'secreto123')->assertRedirect(route('portal.contador'));
    }

    private function loginAttempt(string $identifier, string $password = 'incorrecta'): \Illuminate\Testing\TestResponse
    {
        return $this->from(route('login'))->post(route('login.store'), ['usuario' => $identifier, 'password' => $password]);
    }

    public function test_active_contador_can_login_and_is_redirected_to_main_portal(): void
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

        $response->assertRedirect(route('portal.contador'));
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
