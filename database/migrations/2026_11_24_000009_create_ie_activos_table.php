<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_activos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->restrictOnDelete();
            $table->foreignId('alternativa_id')->nullable()->constrained('ie_alternativas')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->enum('categoria', ['equipo_informatico', 'maquinaria', 'mobiliario', 'vehiculo', 'software', 'licencia', 'otro']);
            $table->enum('clasificacion_contable', ['activo_tangible', 'activo_intangible', 'gasto', 'pendiente_revision']);
            $table->date('fecha_adquisicion')->nullable();
            $table->date('fecha_disponible_uso')->nullable();
            foreach (['costo_adquisicion', 'costos_capitalizables', 'valor_residual'] as $campo) {
                $table->decimal($campo, 20, 6);
            }
            $table->unsignedSmallInteger('vida_util_anios')->nullable();
            $table->boolean('vida_indefinida')->default(false);
            $table->char('moneda', 3);
            $table->decimal('porcentaje_uso', 7, 4);
            $table->enum('estado_activo', ['previsto', 'en_uso', 'fuera_de_uso', 'retirado']);
            $table->enum('origen_dato', ['confirmado', 'estimado', 'provisional']);
            $table->text('observaciones')->nullable();
            $table->text('justificacion_residual')->nullable();
            $table->text('depreciacion_fiscal')->nullable();
            $table->text('amortizacion_fiscal')->nullable();
            $table->text('efecto_tributario')->nullable();
            $table->timestamps();
            $table->index(['proyecto_id', 'id']);
            $table->index('alternativa_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ie_activos ADD CONSTRAINT ie_activos_importes_validos CHECK (
                costo_adquisicion >= 0 AND costos_capitalizables >= 0 AND valor_residual >= 0
                AND valor_residual <= costo_adquisicion + costos_capitalizables
                AND porcentaje_uso BETWEEN 0 AND 100)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_activos');
    }
};
