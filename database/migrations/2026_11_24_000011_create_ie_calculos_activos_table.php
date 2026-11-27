<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_calculos_activos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activo_id')->constrained('ie_activos')->restrictOnDelete();
            $table->foreignId('escenario_id')->nullable()->constrained('ie_escenarios')->restrictOnDelete();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
            $table->enum('metodo', ['comparacion_depreciacion', 'amortizacion_lineal', 'reemplazo']);
            $table->string('version_motor', 24);
            $table->jsonb('instantanea');
            $table->timestampTz('created_at');
            $table->index(['activo_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_calculos_activos');
    }
};
