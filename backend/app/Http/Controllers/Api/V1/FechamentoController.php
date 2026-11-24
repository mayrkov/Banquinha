<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FecharCaixa;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FechamentoController extends Controller
{
    public function show(Request $request, FecharCaixa $fechar)
    {
        return $fechar->resumo($request->user());
    }

    public function store(Request $request, FecharCaixa $fechar)
    {
        $vendedor = $request->user();
        $dados = $request->validate([
            'contagens' => ['required', 'array', 'min:1'],
            'contagens.*.produto_id' => ['required', 'integer', 'distinct', Rule::exists('produtos', 'id')->where('vendedor_id', $vendedor->id)],
            'contagens.*.contagem' => ['required', 'integer', 'min:0', 'max:100000'],
            'contagens.*.motivo' => ['nullable', 'in:doacao,perda,venda_nao_registrada,outro'],
            'contagens.*.preco_unitario' => ['required_if:contagens.*.motivo,venda_nao_registrada', 'nullable', 'integer', 'min:0'],
            'contagens.*.forma_pagamento' => ['required_if:contagens.*.motivo,venda_nao_registrada', 'nullable', 'in:pix,dinheiro,cartao'],
            'contagens.*.metodo_cartao' => ['required_if:contagens.*.forma_pagamento,cartao', 'nullable', 'in:credito,debito'],
            'contagens.*.observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $fechar->ajustar($vendedor, $dados['contagens']);

        return $fechar->resumo($vendedor);
    }
}
