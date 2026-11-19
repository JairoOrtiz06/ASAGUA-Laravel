<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observaciones_reportes', function (Blueprint $table): void {
            $table->increments('id_observacion');
            $table->unsignedInteger('id_reporte');
            $table->unsignedInteger('id_auditor');
            $table->unsignedInteger('id_contador');
            $table->text('comentario');
            $table->string('prioridad', 10);
            $table->timestampTz('fecha_observacion')->nullable();
            $table->string('estado', 15)->default('PENDIENTE');
            $table->timestampTz('fecha_vista')->nullable();
            $table->text('respuesta_contador')->nullable();
            $table->timestampTz('fecha_respuesta')->nullable();

            $table->index('id_reporte', 'observaciones_reporte_index');
            $table->index(['id_contador', 'estado'], 'observaciones_contador_estado_index');
            $table->index('id_auditor', 'observaciones_auditor_index');
            $table->foreign('id_reporte', 'observaciones_reporte_foreign')
                ->references('id')->on('reportes')
                ->restrictOnDelete();
            $table->foreign('id_auditor', 'observaciones_auditor_foreign')
                ->references('id_usuario')->on('tblusuarios')
                ->restrictOnDelete();
            $table->foreign('id_contador', 'observaciones_contador_foreign')
                ->references('id_usuario')->on('tblusuarios')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observaciones_reportes');
    }
};
