<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_escenarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('horizonte_anios');
            $table->enum('periodicidad', ['mensual', 'trimestral', 'anual']);
            $table->decimal('tasa_original', 20, 12)->nullable();
            $table->enum('periodicidad_tasa', ['mensual', 'trimestral', 'semestral', 'anual'])->nullable();
            $table->enum('modalidad_tasa', ['efectiva', 'nominal'])->nullable();
            $table->unsignedSmallInteger('capitalizaciones_anuales')->nullable();
            $table->decimal('tasa_efectiva_anual', 20, 12)->nullable();
            $table->text('fuente_tasa')->nullable();
            $table->enum('estado_tasa', ['pendiente', 'provisional', 'validada'])->default('pendiente');
            $table->boolean('es_principal')->default(false);
            $table->index('proyecto_id');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX ie_escenarios_principal_unique ON ie_escenarios (proyecto_id) WHERE es_principal');
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_escenarios');
    }
};
