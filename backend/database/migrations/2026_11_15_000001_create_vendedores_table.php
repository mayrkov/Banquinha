<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendedores', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('telefone')->nullable();
            $table->string('senha');
            $table->rememberToken();
            $table->text('descricao')->nullable();
            $table->string('chave_pix')->nullable();
            $table->string('nome_recebedor_pix')->nullable();
            $table->string('cidade_recebedor_pix')->nullable();
            $table->string('provedor_cartao')->nullable();
            $table->string('infinitepay_handle')->nullable();
            $table->unsignedInteger('prazo_resposta_min')->default(15);
            $table->unsignedInteger('taxa_entrega')->default(0);
            $table->boolean('em_atividade')->default(false);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendedores');
    }
};
