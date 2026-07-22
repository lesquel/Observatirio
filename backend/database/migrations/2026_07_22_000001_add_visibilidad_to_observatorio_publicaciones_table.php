<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observatorio_publicaciones', function (Blueprint $table) {
            $table->string('visibilidad', 50)->default('publico')->after('fuente');
        });
    }

    public function down(): void
    {
        Schema::table('observatorio_publicaciones', function (Blueprint $table) {
            $table->dropColumn('visibilidad');
        });
    }
};
