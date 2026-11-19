<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_usuario');
            $table->timestampTz('hora')->nullable();
            $table->text('nombre');
            $table->binary('archivo')->nullable();
            $table->boolean('tiene_observaciones')->default(false);
            $table->string('estado_revision', 30)->nullable();

            $table->index('id_usuario', 'reportes_usuario_index');
            $table->index('estado_revision', 'reportes_estado_index');
            $table->foreign('id_usuario', 'reportes_usuario_foreign')
                ->references('id_usuario')->on('tblusuarios')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
