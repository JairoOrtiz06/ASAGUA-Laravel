<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ie_escenarios', function (Blueprint $table): void {
            $table->date('fecha_base')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ie_escenarios', function (Blueprint $table): void {
            $table->dropColumn('fecha_base');
        });
    }
};
