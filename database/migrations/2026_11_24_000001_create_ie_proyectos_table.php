<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_proyectos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('organizacion', 150)->nullable();
            $table->enum('tipo_decision', ['historica', 'prospectiva', 'reemplazo']);
            $table->date('fecha_base');
            $table->unsignedSmallInteger('horizonte_anios');
            $table->enum('estado', ['borrador', 'en_preparacion', 'evaluado'])->default('borrador');
            $table->index(['id_usuario', 'id']);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ie_proyectos');
    }
};
