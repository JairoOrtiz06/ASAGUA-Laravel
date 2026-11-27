<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoDocumental extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ie_historial_documental';

    protected $fillable = ['id_usuario', 'accion', 'datos'];

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
