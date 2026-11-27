<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_analisis_sensibilidad', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->restrictOnDelete();
            $table->foreignId('escenario_id')->constrained('ie_escenarios')->restrictOnDelete();
            $table->foreignId('evaluacion_id')->nullable()->constrained('ie_evaluaciones')->restrictOnDelete();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->enum('perspectiva', ['caja', 'economica']);
            $table->enum('estado', ['provisional', 'validado']);
            $table->string('version_motor', 24);
            $table->jsonb('instantanea');
            $table->timestampTz('created_at');
            $table->index(['proyecto_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_analisis_sensibilidad');
    }
};
