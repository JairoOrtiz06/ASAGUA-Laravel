<?php

namespace App\Policies\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;

class ProyectoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->rol === 'Contador';
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Proyecto $proyecto): bool
    {
        return $this->viewAny($user) && (int) $user->getKey() === (int) $proyecto->id_usuario;
    }

    public function update(User $user, Proyecto $proyecto): bool
    {
        return $this->view($user, $proyecto);
    }

    public function delete(User $user, Proyecto $proyecto): bool
    {
        return $this->view($user, $proyecto);
    }
}
