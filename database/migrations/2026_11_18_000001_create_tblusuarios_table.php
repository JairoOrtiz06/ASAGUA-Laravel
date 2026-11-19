<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL folds the original unquoted tblUsuarios to tblusuarios.
        Schema::create('tblusuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->text('nombre');
            $table->text('apellido');
            $table->string('email', 255);
            $table->string('usuario', 100);
            $table->string('password', 255);
            $table->string('rol', 20);
            $table->boolean('activo')->default(true);

            $table->unique('email', 'tblusuarios_email_unique');
            $table->unique('usuario', 'tblusuarios_usuario_unique');
            $table->index('rol', 'tblusuarios_rol_index');
            $table->index('activo', 'tblusuarios_activo_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblusuarios');
    }
};
