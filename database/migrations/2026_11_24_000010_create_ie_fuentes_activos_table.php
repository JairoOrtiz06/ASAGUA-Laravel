<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_fuentes_activos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activo_id')->constrained('ie_activos')->restrictOnDelete();
            $table->string('uso', 30);
            $table->string('tipo', 30);
            $table->string('referencia', 255);
            $table->date('fecha');
            $table->decimal('importe_observado', 20, 6)->nullable();
            $table->unsignedInteger('id_documento')->nullable();
            $table->unsignedInteger('id_detalle')->nullable();
            $table->foreign('id_documento')->references('id_documento')->on('tbldocumentos_fuente')->nullOnDelete();
            $table->foreign('id_detalle')->references('id_detalle')->on('tbldetalles_partida')->nullOnDelete();
            $table->text('justificacion');
            $table->timestamps();
            $table->index('activo_id');
            $table->index('id_documento');
            $table->index('id_detalle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_fuentes_activos');
    }
};
