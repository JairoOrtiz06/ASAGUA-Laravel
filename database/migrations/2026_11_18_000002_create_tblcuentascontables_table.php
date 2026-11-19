<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tblcuentascontables', function (Blueprint $table): void {
            $table->increments('id_cuenta');
            $table->string('codigo_cuenta', 50);
            $table->text('nombre_cuenta');
            $table->string('tipo_cuenta', 30);
            $table->integer('nivel');
            $table->unsignedInteger('id_cuenta_padre')->nullable();
            $table->boolean('estado')->default(true);

            $table->index('codigo_cuenta', 'tblcuentas_codigo_index');
            $table->index('id_cuenta_padre', 'tblcuentas_padre_index');
            $table->index('estado', 'tblcuentas_estado_index');
            $table->foreign('id_cuenta_padre', 'tblcuentas_padre_foreign')
                ->references('id_cuenta')->on('tblcuentascontables')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblcuentascontables');
    }
};
