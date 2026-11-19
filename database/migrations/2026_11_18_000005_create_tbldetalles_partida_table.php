<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbldetalles_partida', function (Blueprint $table): void {
            $table->increments('id_detalle');
            $table->unsignedInteger('id_partida');
            $table->unsignedInteger('id_cuenta');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->unsignedInteger('id_documento')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('debe', 18, 2)->default(0);
            $table->decimal('haber', 18, 2)->default(0);
            $table->timestampTz('fecha_creacion')->nullable();

            $table->index('id_partida', 'tbldetalles_partida_index');
            $table->index('id_cuenta', 'tbldetalles_cuenta_index');
            $table->index('id_usuario', 'tbldetalles_usuario_index');
            $table->index('id_documento', 'tbldetalles_documento_index');
            $table->index('fecha_creacion', 'tbldetalles_fecha_index');
            $table->foreign('id_partida', 'tbldetalles_partida_foreign')
                ->references('id_partida')->on('tblpartidas')
                ->restrictOnDelete();
            $table->foreign('id_cuenta', 'tbldetalles_cuenta_foreign')
                ->references('id_cuenta')->on('tblcuentascontables')
                ->restrictOnDelete();
            $table->foreign('id_usuario', 'tbldetalles_usuario_foreign')
                ->references('id_usuario')->on('tblusuarios')
                ->nullOnDelete();
            $table->foreign('id_documento', 'tbldetalles_documento_foreign')
                ->references('id_documento')->on('tbldocumentos_fuente')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbldetalles_partida');
    }
};
