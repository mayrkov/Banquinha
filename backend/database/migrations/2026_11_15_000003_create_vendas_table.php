<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores');
            $table->string('origem');
            $table->string('forma_pagamento')->nullable();
            $table->unsignedInteger('taxa_entrega')->default(0);
            $table->unsignedInteger('valor_total');
            $table->string('status');
            $table->timestamp('pago_em')->nullable();
            $table->timestamp('criado_em');
            $table->string('id_local')->nullable();

            // D36: reenvio da fila offline não duplica a venda.
            $table->unique(['vendedor_id', 'id_local']);
        });

        Schema::create('itens_venda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->unsignedInteger('quantidade');
            $table->unsignedInteger('preco_padrao');
            $table->unsignedInteger('preco_unitario');
        });

        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->string('provedor');
            $table->string('metodo');
            $table->unsignedInteger('valor');
            $table->unsignedSmallInteger('parcelas')->nullable();
            $table->string('status');
            $table->string('id_externo')->nullable();
            $table->string('codigo_autorizacao')->nullable();
            $table->string('bandeira')->nullable();
            $table->string('link')->nullable();
            $table->json('dados_retorno')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->timestamp('criado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
        Schema::dropIfExists('itens_venda');
        Schema::dropIfExists('vendas');
    }
};
