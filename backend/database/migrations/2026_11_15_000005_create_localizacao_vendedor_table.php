<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha por vendedor: só a posição atual (D37: decimal no MVP).
        Schema::create('localizacao_vendedor', function (Blueprint $table) {
            $table->foreignId('vendedor_id')->primary()->constrained('vendedores')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('atualizado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localizacao_vendedor');
    }
};
