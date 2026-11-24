<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fluxo 6: diferença entre a contagem física e o estoque do sistema, com o motivo.
        Schema::create('ajustes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores');
            $table->foreignId('produto_id')->constrained('produtos');
            $table->foreignId('turno_id')->nullable()->constrained('turnos');
            $table->foreignId('venda_id')->nullable()->constrained('vendas');
            $table->string('motivo'); // doacao, perda, venda_nao_registrada, outro
            $table->integer('quantidade'); // contagem − estoque do sistema (negativo = saiu)
            $table->unsignedInteger('preco_padrao');
            $table->string('observacao')->nullable();
            $table->timestamp('criado_em');
            $table->index(['vendedor_id', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_estoque');
    }
};
