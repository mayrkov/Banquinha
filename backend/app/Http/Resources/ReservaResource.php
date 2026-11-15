<?php

namespace App\Http\Resources;

use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reserva
 */
class ReservaResource extends JsonResource
{
    /** true = visão do vendedor (vê o cliente; nunca o código de entrega). */
    public bool $paraVendedor = false;

    public static function doVendedor(Reserva $reserva): static
    {
        $r = new static($reserva);
        $r->paraVendedor = true;

        return $r;
    }

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('itens.produto', 'parada', $this->paraVendedor ? 'cliente' : 'vendedor');

        return [
            'codigo' => $this->codigo,
            'status' => $this->status,
            'tipo' => $this->tipo,
            'itens' => $this->itens->map(fn ($i) => [
                'produto_id' => $i->produto_id,
                'nome' => $i->produto->nome,
                'cor' => $i->produto->cor,
                'quantidade' => $i->quantidade,
                'preco_unitario' => $i->preco_unitario,
            ]),
            'parada' => $this->parada?->only('id', 'local', 'inicio', 'fim'),
            'endereco_entrega' => $this->endereco_entrega,
            'referencia_entrega' => $this->referencia_entrega,
            'taxa_entrega' => $this->taxa_entrega,
            'valor_total' => $this->valor_total,
            'responder_ate' => $this->responder_ate,
            'cliente_informou_pagamento_em' => $this->cliente_informou_pagamento_em,
            'criado_em' => $this->criado_em,
            'encerrada_em' => $this->encerrada_em,
            $this->mergeWhen($this->paraVendedor, fn () => [
                'cliente' => $this->cliente->only('nome', 'telefone'),
            ]),
            $this->mergeWhen(! $this->paraVendedor, fn () => [
                'vendedor' => $this->vendedor->only('nome', 'slug', 'telefone'),
                'codigo_entrega' => $this->codigo_entrega,
                // Pix só depois do aceite (regra de pagamento 2). O BR Code é montado no navegador (D44).
                'pix' => $this->status === 'aceito' ? [
                    'chave' => $this->vendedor->chave_pix,
                    'nome' => $this->vendedor->nome_recebedor_pix,
                    'cidade' => $this->vendedor->cidade_recebedor_pix,
                ] : null,
            ]),
        ];
    }
}
