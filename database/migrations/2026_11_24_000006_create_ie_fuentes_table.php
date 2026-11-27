<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_fuentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concepto_id')->constrained('ie_conceptos')->restrictOnDelete();
            $table->enum('tipo', ['factura', 'cotizacion', 'comprobante', 'entrevista', 'registro_contable', 'estimacion']);
            $table->string('referencia', 255);
            $table->unsignedInteger('id_documento')->nullable();
            $table->unsignedInteger('id_detalle')->nullable();
            $table->foreign('id_documento')->references('id_documento')->on('tbldocumentos_fuente')->nullOnDelete();
            $table->foreign('id_detalle')->references('id_detalle')->on('tbldetalles_partida')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('importe_observado', 20, 6)->nullable();
            $table->decimal('importe_utilizado', 20, 6);
            $table->text('justificacion');
            $table->timestamps();
            $table->index('concepto_id');
            $table->index('id_documento');
            $table->index('id_detalle');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ie_fuentes ADD CONSTRAINT ie_fuentes_importes_positivos CHECK ((importe_observado IS NULL OR importe_observado >= 0) AND importe_utilizado >= 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_fuentes');
    }
};
