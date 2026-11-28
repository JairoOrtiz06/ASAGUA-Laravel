<?php

namespace App\Http\Controllers;

use App\Http\Requests\CuentaContableRequest;
use App\Models\CuentaContable;
use App\Services\PartidaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CuentaContableController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $estado = $request->input('estado', 'activos');

        $cuentas = CuentaContable::query()
            ->with('padre')
            ->withCount(['hijos', 'detalles'])
            ->when($estado === 'activos', fn ($query) => $query->where('estado', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('estado', false))
            ->when($search !== '', function ($query) use ($search): void {
                $like = "%{$search}%";
                $query->where(function ($nested) use ($like): void {
                    $nested->whereRaw('LOWER(codigo_cuenta) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(nombre_cuenta) LIKE LOWER(?)', [$like]);
                });
            })
            ->orderBy('codigo_cuenta')
            ->paginate(25)
            ->withQueryString();

        return view('catalogo.index', compact('cuentas', 'search', 'estado'));
    }

    public function create(): View
    {
        $padres = CuentaContable::query()->orderBy('codigo_cuenta')->get();

        return view('catalogo.create', compact('padres'));
    }

    public function store(CuentaContableRequest $request): RedirectResponse
    {
        CuentaContable::create($request->validated());

        return redirect()->route('catalogo.index')->with('status', 'Cuenta registrada correctamente.');
    }

    public function edit(CuentaContable $cuenta): View
    {
        $padres = CuentaContable::query()
            ->where($cuenta->getKeyName(), '!=', $cuenta->getKey())
            ->orderBy('codigo_cuenta')
            ->get();

        return view('catalogo.edit', compact('cuenta', 'padres'));
    }

    public function update(CuentaContableRequest $request, CuentaContable $cuenta): RedirectResponse
    {
        DB::transaction(function () use ($request, $cuenta): void {
            PartidaService::bloquearRegistro();
            $cuenta->refresh()->fill($request->validated());

            if ($cuenta->isDirty(['codigo_cuenta', 'nombre_cuenta', 'tipo_cuenta', 'nivel', 'id_cuenta_padre'])) {
                $padres = CuentaContable::query()->pluck('id_cuenta_padre', 'id_cuenta');
                $registradas = DB::table('tbldetalles_partida')->distinct()->pluck('id_cuenta');
                $visitadas = [];

                foreach ($registradas as $id) {
                    while ($id !== null && ! isset($visitadas[$id])) {
                        if ((int) $id === (int) $cuenta->getKey()) {
                            throw ValidationException::withMessages([
                                'codigo_cuenta' => 'La cuenta o una de sus subcuentas tiene movimientos registrados. Solo se puede cambiar su estado.',
                            ]);
                        }
                        $visitadas[$id] = true;
                        $id = $padres->get($id);
                    }
                }
            }

            $cuenta->save();
        });

        return redirect()->route('catalogo.index')->with('status', 'Cuenta actualizada correctamente.');
    }

    public function destroy(CuentaContable $cuenta): RedirectResponse
    {
        if ($cuenta->hijos()->exists() || $cuenta->detalles()->exists()) {
            return back()->with('error', 'No se puede eliminar una cuenta con subcuentas o movimientos contables.');
        }

        try {
            $cuenta->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar la cuenta porque está referenciada.');
        }

        return redirect()->route('catalogo.index')->with('status', 'Cuenta eliminada correctamente.');
    }
}
