<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_preferencias_evaluacion', function (Blueprint $table): void {
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->cascadeOnDelete();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->cascadeOnDelete();
            $table->jsonb('seleccion');
            $table->primary(['proyecto_id', 'id_usuario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_preferencias_evaluacion');
    }
};
