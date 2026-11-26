<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ie_conceptos', function (Blueprint $table): void {
            $table->foreignId('activo_id')->nullable()->constrained('ie_activos')->restrictOnDelete();
            $table->string('activo_operacion', 30)->nullable();
            $table->date('fecha_programada')->nullable();
            $table->index('activo_id');
        });
    }

    public function down(): void
    {
        Schema::table('ie_conceptos', function (Blueprint $table): void {
            $table->dropIndex(['activo_id']);
            $table->dropConstrainedForeignId('activo_id');
            $table->dropColumn(['activo_operacion', 'fecha_programada']);
        });
    }
};
