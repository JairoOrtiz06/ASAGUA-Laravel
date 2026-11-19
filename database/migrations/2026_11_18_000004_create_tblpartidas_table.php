<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tblpartidas', function (Blueprint $table): void {
            $table->increments('id_partida');
            $table->unsignedInteger('id_usuario');
            $table->integer('numero_partida');
            $table->date('fecha');
            $table->text('concepto');

            $table->index('id_usuario', 'tblpartidas_usuario_index');
            $table->index('fecha', 'tblpartidas_fecha_index');
            $table->index(['fecha', 'numero_partida'], 'tblpartidas_fecha_numero_index');
            $table->foreign('id_usuario', 'tblpartidas_usuario_foreign')
                ->references('id_usuario')->on('tblusuarios')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblpartidas');
    }
};
