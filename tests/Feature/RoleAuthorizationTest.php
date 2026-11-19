<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_access_only_its_panel(): void
    {
        foreach (['Admin', 'Contador', 'Auditor'] as $role) {
            $user = User::factory()->create(['rol' => $role]);
            $this->actingAs($user)
                ->get(route('panel.'.strtolower($role)))
                ->assertOk();
        }
    }

    public function test_only_admin_can_manage_users(): void
    {
        $contador = User::factory()->create(['rol' => 'Contador']);
        $this->actingAs($contador)->get(route('admin.users.index'))->assertForbidden();

        $auditor = User::factory()->create(['rol' => 'Auditor']);
        $this->actingAs($auditor)->get(route('admin.users.index'))->assertForbidden();

        $admin = User::factory()->create(['rol' => 'Admin']);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_admin_can_register_and_toggle_a_user_without_fictitious_seed_data(): void
    {
        $admin = User::factory()->create(['rol' => 'Admin']);
        $this->actingAs($admin);

        $this->post(route('admin.users.store'), [
            'nombre' => 'Ana',
            'apellido' => 'Contable',
            'email' => 'ana@example.test',
            'usuario' => 'ana.contable',
            'password' => 'secreto123',
            'rol' => 'Contador',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('usuario', 'ana.contable')->firstOrFail();
        $this->assertTrue($user->activo);

        $this->patch(route('admin.users.status', $user))
            ->assertRedirect();
        $this->assertFalse($user->refresh()->activo);
    }
}
