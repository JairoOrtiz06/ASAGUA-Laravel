<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Partida extends Model
{
    use HasFactory;

    protected $table = 'tblpartidas';
    protected $primaryKey = 'id_partida';
    public $timestamps = false;

    protected $fillable = ['id_usuario', 'numero_partida', 'fecha', 'concepto'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'numero_partida' => 'integer'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePartida::class, 'id_partida', 'id_partida');
    }

    public function scopeAjustes(Builder $query): Builder
    {
        return $query->where('concepto', 'like', '[AJUSTE:%');
    }

    public function esAjuste(): bool
    {
        return preg_match('/^\[AJUSTE:[^\]]+\]/', (string) $this->concepto) === 1;
    }

    public function getTipoAjusteAttribute(): ?string
    {
        if (preg_match('/^\[AJUSTE:([^\]]+)\]/', (string) $this->concepto, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function getConceptoAjusteAttribute(): string
    {
        return preg_replace('/^\[AJUSTE:[^\]]+\]\s*/', '', (string) $this->concepto) ?? (string) $this->concepto;
    }
}
