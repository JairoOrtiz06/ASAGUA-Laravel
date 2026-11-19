<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentaContableTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_roles_can_consult_active_accounts(): void
    {
        $active = CuentaContable::create([
            'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja',
            'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => true,
        ]);
        CuentaContable::create([
            'codigo_cuenta' => '1102', 'nombre_cuenta' => 'Cuenta inactiva',
            'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => false,
        ]);

        foreach (['Admin', 'Contador', 'Auditor'] as $role) {
            $this->actingAs(User::factory()->create(['rol' => $role]))
                ->get(route('catalogo.index'))
                ->assertOk()
                ->assertSee($active->nombre_cuenta)
                ->assertDontSee('Cuenta inactiva');
        }
    }

    public function test_only_admin_can_create_update_and_delete_accounts(): void
    {
        $contador = User::factory()->create(['rol' => 'Contador']);
        $this->actingAs($contador)->get(route('admin.cuentas.create'))->assertForbidden();

        $admin = User::factory()->create(['rol' => 'Admin']);
        $this->actingAs($admin);

        $this->post(route('admin.cuentas.store'), [
            'codigo_cuenta' => '2101', 'nombre_cuenta' => 'Proveedores',
            'tipo_cuenta' => 'Pasivo', 'nivel' => 3,
            'id_cuenta_padre' => '', 'estado' => 1,
        ])->assertRedirect(route('catalogo.index'));

        $account = CuentaContable::where('codigo_cuenta', '2101')->firstOrFail();
        $this->put(route('admin.cuentas.update', $account), [
            'codigo_cuenta' => '2102', 'nombre_cuenta' => 'Proveedores actualizados',
            'tipo_cuenta' => 'Pasivo', 'nivel' => 3,
            'id_cuenta_padre' => '', 'estado' => 1,
        ])->assertRedirect(route('catalogo.index'));

        $this->assertDatabaseHas('tblcuentascontables', [
            'id_cuenta' => $account->id_cuenta,
            'codigo_cuenta' => '2102',
        ]);

        $this->delete(route('admin.cuentas.destroy', $account))
            ->assertRedirect(route('catalogo.index'));
        $this->assertDatabaseMissing('tblcuentascontables', ['id_cuenta' => $account->id_cuenta]);
    }

    public function test_invalid_account_data_is_rejected(): void
    {
        $admin = User::factory()->create(['rol' => 'Admin']);

        $this->actingAs($admin)
            ->post(route('admin.cuentas.store'), [
                'codigo_cuenta' => '', 'nombre_cuenta' => '',
                'tipo_cuenta' => '', 'nivel' => 'texto',
                'estado' => 1,
            ])
            ->assertSessionHasErrors(['codigo_cuenta', 'nombre_cuenta', 'tipo_cuenta', 'nivel']);
    }

    public function test_account_with_children_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['rol' => 'Admin']);
        $parent = CuentaContable::create([
            'codigo_cuenta' => '1', 'nombre_cuenta' => 'Activo',
            'tipo_cuenta' => 'Activo', 'nivel' => 1, 'estado' => true,
        ]);
        $child = CuentaContable::create([
            'codigo_cuenta' => '11', 'nombre_cuenta' => 'Activo corriente',
            'tipo_cuenta' => 'Activo', 'nivel' => 2,
            'id_cuenta_padre' => $parent->id_cuenta, 'estado' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.cuentas.destroy', $parent))
            ->assertRedirect();

        $this->assertDatabaseHas('tblcuentascontables', ['id_cuenta' => $parent->id_cuenta]);
        $this->assertDatabaseHas('tblcuentascontables', ['id_cuenta' => $child->id_cuenta]);
    }

    public function test_account_cannot_become_its_own_parent_or_create_a_cycle(): void
    {
        $admin = User::factory()->create(['rol' => 'Admin']);
        $parent = CuentaContable::create([
            'codigo_cuenta' => '1', 'nombre_cuenta' => 'Activo',
            'tipo_cuenta' => 'Activo', 'nivel' => 1, 'estado' => true,
        ]);
        $child = CuentaContable::create([
            'codigo_cuenta' => '11', 'nombre_cuenta' => 'Activo corriente',
            'tipo_cuenta' => 'Activo', 'nivel' => 2,
            'id_cuenta_padre' => $parent->id_cuenta, 'estado' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.cuentas.update', $parent), [
                'codigo_cuenta' => '1', 'nombre_cuenta' => 'Activo',
                'tipo_cuenta' => 'Activo', 'nivel' => 1,
                'id_cuenta_padre' => $child->id_cuenta, 'estado' => 1,
            ])
            ->assertSessionHasErrors('id_cuenta_padre');
    }
}
