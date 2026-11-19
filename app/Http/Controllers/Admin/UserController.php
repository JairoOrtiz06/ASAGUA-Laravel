<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserFormRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $like = "%{$search}%";
                $query->where(function ($nested) use ($like): void {
                    foreach (['usuario', 'nombre', 'apellido', 'email'] as $field) {
                        $nested->orWhereRaw("LOWER({$field}) LIKE LOWER(?)", [$like]);
                    }
                });
            })
            ->orderByDesc('id_usuario')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(UserFormRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['activo'] = true;

        User::create($data);

        return redirect()->route('admin.users.index')->with('status', 'Usuario registrado correctamente.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UserFormRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'Usuario actualizado correctamente.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $user->update(['activo' => ! $user->activo]);

        return back()->with('status', $user->activo
            ? 'Usuario activado correctamente.'
            : 'Usuario desactivado correctamente.');
    }
}
