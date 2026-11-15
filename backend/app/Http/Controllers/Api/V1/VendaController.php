<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarVendaPresencial;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendaController extends Controller
{
    /**
     * Registra uma venda presencial paga. Reenviar o mesmo id_local devolve a venda já gravada (200).
     */
    public function store(Request $request, RegistrarVendaPresencial $registrar)
    {
        $vendedor = $request->user();

        $dados = $request->validate([
            'id_local' => ['required', 'uuid'],
            'forma_pagamento' => ['required', 'in:pix,dinheiro,cartao'],
            'metodo_cartao' => ['required_if:forma_pagamento,cartao', 'nullable', 'in:credito,debito'],
            // Hora do celular; tolerância para relógio adiantado.
            'pago_em' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(10)->toIso8601String()],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer', 'distinct', Rule::exists('produtos', 'id')->where('vendedor_id', $vendedor->id)],
            'itens.*.quantidade' => ['required', 'integer', 'min:1', 'max:1000'],
            'itens.*.preco_unitario' => ['required', 'integer', 'min:0'],
        ]);

        [$venda, $criada] = $registrar($vendedor, $dados);

        return response()->json($venda, $criada ? 201 : 200);
    }
}
