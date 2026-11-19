<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbldocumentos_fuente', function (Blueprint $table): void {
            $table->increments('id_documento');
            $table->text('nombre_documento');
            $table->text('descripcion')->nullable();
            $table->string('tipo_documento', 50);
            $table->timestampTz('fecha_creacion')->nullable();
            $table->binary('archivo')->nullable();

            $table->index('fecha_creacion', 'tbldocumentos_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbldocumentos_fuente');
    }
};
