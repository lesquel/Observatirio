<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conservar solo la asignación más reciente por usuario
        $duplicates = DB::table('usuario_departamento')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicates as $userId) {
            $keepId = DB::table('usuario_departamento')
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->value('id');

            if ($keepId) {
                DB::table('usuario_departamento')
                    ->where('user_id', $userId)
                    ->where('id', '!=', $keepId)
                    ->delete();
            }
        }

        Schema::table('usuario_departamento', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'departamento_id']);
            $table->unique('user_id');
        });

        DB::table('publicacion_contadores')->insertOrIgnore([
            ['tipo' => 'ATLAS', 'siguiente_numero' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::table('usuario_departamento', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'departamento_id']);
        });

        DB::table('publicacion_contadores')->where('tipo', 'ATLAS')->delete();
    }
};
