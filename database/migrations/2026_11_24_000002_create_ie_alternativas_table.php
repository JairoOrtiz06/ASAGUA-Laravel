<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_alternativas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('proveedor', 150)->nullable();
            $table->text('descripcion')->nullable();
            $table->enum('estado_implementacion', ['no_implementada', 'en_proceso', 'implementada', 'retirada']);
            $table->date('fecha_implementacion')->nullable();
            $table->enum('viabilidad_tecnica', ['pendiente', 'viable', 'no_viable']);
            $table->text('observaciones')->nullable();
            $table->index('proyecto_id');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ie_alternativas');
    }
};
