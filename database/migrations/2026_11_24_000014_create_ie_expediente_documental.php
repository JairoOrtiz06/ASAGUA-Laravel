<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_documentos_economicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->restrictOnDelete();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
            $table->string('titulo', 160);
            $table->string('tipo', 32);
            $table->string('procedencia', 250);
            $table->date('fecha_documento');
            $table->string('calidad', 24);
            $table->enum('estado', ['pendiente', 'existente', 'revisado', 'validado']);
            $table->text('observaciones')->nullable();
            $table->string('archivo', 100)->nullable();
            $table->string('mime', 40)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestampsTz();
            $table->index(['proyecto_id', 'id']);
        });

        Schema::create('ie_vinculos_documentales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidencia_id')->constrained('ie_documentos_economicos')->restrictOnDelete();
            $table->string('tipo', 32);
            $table->unsignedBigInteger('destino_id');
            $table->string('etiqueta', 255);
            $table->string('conciliacion', 24)->nullable();
            $table->text('nota');
            $table->timestampsTz();
            $table->unique(['evidencia_id', 'tipo', 'destino_id'], 'ie_vinculo_documental_unico');
            $table->index(['tipo', 'destino_id']);
        });

        Schema::create('ie_historial_documental', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidencia_id')->constrained('ie_documentos_economicos')->restrictOnDelete();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
            $table->string('accion', 30);
            $table->jsonb('datos');
            $table->timestampTz('created_at');
            $table->index(['evidencia_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_historial_documental');
        Schema::dropIfExists('ie_vinculos_documentales');
        Schema::dropIfExists('ie_documentos_economicos');
    }
};
