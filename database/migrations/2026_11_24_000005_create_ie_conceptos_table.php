<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_conceptos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('ie_proyectos')->restrictOnDelete();
            $table->foreignId('alternativa_id')->nullable()->constrained('ie_alternativas')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->enum('tipo_concepto', ['inversion_inicial', 'ingreso', 'ahorro', 'costo_operativo', 'mantenimiento', 'inversion_adicional', 'valor_rescate', 'otro']);
            $table->enum('direccion_otro', ['entrada', 'salida'])->nullable();
            $table->decimal('monto', 20, 6);
            $table->char('moneda', 3);
            $table->enum('periodicidad', ['unico', 'mensual', 'trimestral', 'semestral', 'anual']);
            $table->enum('momento_pago', ['inicio', 'fin']);
            $table->unsignedSmallInteger('periodo_inicio')->nullable();
            $table->unsignedSmallInteger('periodo_fin')->nullable();
            $table->date('fecha_real')->nullable();
            $table->unsignedSmallInteger('numero_ocurrencias')->nullable();
            $table->decimal('porcentaje_crecimiento', 14, 12)->nullable();
            $table->enum('clasificacion_decision', ['diferencial', 'comun', 'hundido', 'pendiente_clasificacion']);
            $table->enum('naturaleza_economica', ['desembolso_real', 'ingreso_real', 'costo_oportunidad', 'ahorro_estimado', 'no_monetario']);
            $table->enum('estado_dato', ['confirmado', 'estimado', 'provisional']);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->index(['proyecto_id', 'alternativa_id']);
            $table->index('alternativa_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ie_conceptos ADD CONSTRAINT ie_conceptos_monto_positivo CHECK (monto >= 0)");
            DB::statement("ALTER TABLE ie_conceptos ADD CONSTRAINT ie_conceptos_ambito CHECK (((alternativa_id IS NULL) = (clasificacion_decision = 'comun')))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_conceptos');
    }
};
