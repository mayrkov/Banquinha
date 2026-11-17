<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Caminho no disco "public" (ex.: produtos/abc.jpg). Foto redonda na página do vendedor.
            $table->string('foto')->nullable()->after('cor');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
