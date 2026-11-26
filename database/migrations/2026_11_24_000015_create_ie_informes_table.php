<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_informes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->restrictOnDelete();
            $table->foreignId('evaluacion_id')->constrained('ie_evaluaciones')->restrictOnDelete();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
            $table->enum('estado', ['preliminar', 'validado']);
            $table->uuid('folio')->unique();
            $table->char('sha256', 64);
            $table->jsonb('instantanea');
            $table->timestampTz('created_at');
            $table->index(['proyecto_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_informes');
    }
};
