<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedores', function (Blueprint $table) {
            // Endereço público: /v/{slug}.
            $table->string('slug')->nullable()->unique()->after('nome');
        });

        Schema::table('produtos', function (Blueprint $table) {
            // Cor do sabor na página do cliente (ex.: #5A3324).
            $table->string('cor', 7)->nullable()->after('descricao');
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('telefone');
            $table->string('senha');
            $table->rememberToken();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores');
            $table->timestamp('inicio');
            $table->timestamp('fim')->nullable();
        });

        Schema::create('paradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores');
            $table->string('local');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('inicio');
            $table->timestamp('fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->nullable();
        });

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('codigo')->unique();
            $table->string('tipo');
            $table->foreignId('parada_id')->nullable()->constrained('paradas');
            $table->text('endereco_entrega')->nullable();
            $table->string('referencia_entrega')->nullable();
            $table->timestamp('endereco_apagado_em')->nullable();
            $table->unsignedInteger('taxa_entrega')->default(0);
            $table->unsignedInteger('valor_total');
            $table->string('status')->index();
            $table->timestamp('responder_ate');
            $table->timestamp('cliente_informou_pagamento_em')->nullable();
            $table->string('codigo_entrega', 4)->nullable();
            $table->timestamp('encerrada_em')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('itens_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->unsignedInteger('quantidade');
            $table->unsignedInteger('preco_unitario');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('reserva_id')->nullable()->after('origem')->constrained('reservas');
            $table->foreignId('turno_id')->nullable()->after('reserva_id')->constrained('turnos');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_id');
            $table->dropConstrainedForeignId('reserva_id');
        });
        Schema::dropIfExists('itens_reserva');
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('paradas');
        Schema::dropIfExists('turnos');
        Schema::dropIfExists('clientes');
        Schema::table('produtos', fn (Blueprint $t) => $t->dropColumn('cor'));
        Schema::table('vendedores', fn (Blueprint $t) => $t->dropColumn('slug'));
    }
};
