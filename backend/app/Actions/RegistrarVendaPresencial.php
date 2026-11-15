<?php

namespace App\Actions;

use App\Models\Produto;
use App\Models\Venda;
use App\Models\Vendedor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RegistrarVendaPresencial
{
    /**
     * Registra uma venda presencial já paga (o vendedor confirmou no PDV, possivelmente offline).
     * Idempotente por id_local (D36). Retorna [venda, criada].
     *
     * @param  array{id_local: string, forma_pagamento: string, metodo_cartao?: string|null, pago_em: string, itens: list<array{produto_id: int, quantidade: int, preco_unitario: int}>}  $dados
     * @return array{0: Venda, 1: bool}
     */
    public function __invoke(Vendedor $vendedor, array $dados): array
    {
        if ($existente = $this->buscar($vendedor, $dados['id_local'])) {
            return [$existente, false];
        }

        try {
            $venda = DB::transaction(fn () => $this->criar($vendedor, $dados));
        } catch (UniqueConstraintViolationException) {
            // Dois envios simultâneos da mesma venda: o outro gravou primeiro.
            return [$this->buscar($vendedor, $dados['id_local']), false];
        }

        return [$venda, true];
    }

    private function criar(Vendedor $vendedor, array $dados): Venda
    {
        $produtos = Produto::where('vendedor_id', $vendedor->id)
            ->whereIn('id', array_column($dados['itens'], 'produto_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $total = array_sum(array_map(fn ($i) => $i['quantidade'] * $i['preco_unitario'], $dados['itens']));

        $venda = $vendedor->vendas()->create([
            'origem' => 'presencial',
            'turno_id' => $vendedor->turnoAberto()?->id,
            'forma_pagamento' => $dados['forma_pagamento'],
            'valor_total' => $total,
            'status' => 'paga',
            'pago_em' => $dados['pago_em'],
            'id_local' => $dados['id_local'],
        ]);

        foreach ($dados['itens'] as $item) {
            $produto = $produtos[$item['produto_id']];
            $venda->itens()->create([
                'produto_id' => $produto->id,
                'quantidade' => $item['quantidade'],
                'preco_padrao' => $produto->preco,
                'preco_unitario' => $item['preco_unitario'],
            ]);
            // Pode ficar negativo: a venda já aconteceu na rua; o ajuste de estoque corrige a contagem.
            $produto->decrement('estoque', $item['quantidade']);
        }

        $venda->pagamentos()->create([
            'provedor' => match ($dados['forma_pagamento']) {
                'pix' => 'pix_estatico',
                'cartao' => $vendedor->provedor_cartao === 'infinitepay' ? 'infinitetap' : 'manual',
                default => 'manual',
            },
            'metodo' => $dados['forma_pagamento'] === 'cartao' ? $dados['metodo_cartao'] : $dados['forma_pagamento'],
            'valor' => $total,
            'status' => 'aprovado',
            'pago_em' => $dados['pago_em'],
        ]);

        return $venda->load('itens', 'pagamentos');
    }

    private function buscar(Vendedor $vendedor, string $idLocal): ?Venda
    {
        return $vendedor->vendas()->where('id_local', $idLocal)->with('itens', 'pagamentos')->first();
    }
}
