<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_flujos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alternativa_id')->constrained('ie_alternativas')->restrictOnDelete();
            $table->foreignId('escenario_id')->constrained('ie_escenarios')->restrictOnDelete();
            $table->unsignedInteger('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->date('fecha_flujo');
            $table->enum('tipo_concepto', ['inversion_inicial', 'ingreso', 'ahorro', 'costo_operativo', 'mantenimiento', 'inversion_adicional', 'valor_rescate', 'otro']);
            $table->enum('direccion_otro', ['entrada', 'salida'])->nullable();
            $table->decimal('monto', 20, 6);
            $table->char('moneda', 3);
            $table->enum('clasificacion_decision', ['diferencial', 'hundido', 'pendiente_clasificacion']);
            $table->enum('naturaleza_economica', ['desembolso_real', 'ingreso_real', 'costo_oportunidad', 'ahorro_estimado', 'no_monetario']);
            $table->enum('estado_dato', ['confirmado', 'estimado', 'provisional']);
            $table->boolean('observado')->default(false);
            $table->text('justificacion');
            $table->timestamps();
            $table->index(['escenario_id', 'alternativa_id', 'fecha_flujo']);
            $table->index('alternativa_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ie_flujos ADD CONSTRAINT ie_flujos_monto_positivo CHECK (monto >= 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_flujos');
    }
};
