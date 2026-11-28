<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tblpartidas', function (Blueprint $table): void {
            $table->string('tipo_ajuste')->nullable();
        });

        DB::table('tblpartidas')->where('concepto', 'like', '[AJUSTE:%')
            ->select('id_partida', 'concepto')->chunkById(500, function ($partidas): void {
                foreach ($partidas as $partida) {
                    if (preg_match('/^\[AJUSTE:([^\]]{1,255})\]/u', $partida->concepto, $matches) === 1) {
                        DB::table('tblpartidas')->where('id_partida', $partida->id_partida)
                            ->update(['tipo_ajuste' => $matches[1]]);
                    }
                }
            }, 'id_partida');

        Schema::create('tblcierres_contables', function (Blueprint $table): void {
            $table->date('fecha_corte')->primary();
            $table->unsignedInteger('id_usuario');
            $table->timestampTz('registrado_en');
            $table->foreign('id_usuario')->references('id_usuario')->on('tblusuarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblcierres_contables');
        Schema::table('tblpartidas', function (Blueprint $table): void {
            $table->dropColumn('tipo_ajuste');
        });
    }
};
