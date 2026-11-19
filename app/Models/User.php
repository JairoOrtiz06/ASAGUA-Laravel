<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'tblusuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;
    protected $rememberTokenName = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'usuario',
        'password',
        'rol',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function partidas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Partida::class, 'id_usuario', 'id_usuario');
    }

    public function detallesPartida(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DetallePartida::class, 'id_usuario', 'id_usuario');
    }

    public function reportes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reporte::class, 'id_usuario', 'id_usuario');
    }

    public function observacionesComoAuditor(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ObservacionReporte::class, 'id_auditor', 'id_usuario');
    }

    public function observacionesComoContador(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ObservacionReporte::class, 'id_contador', 'id_usuario');
    }
}
