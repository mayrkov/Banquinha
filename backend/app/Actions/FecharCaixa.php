<?php

namespace App\Actions;

use App\Models\Produto;
use App\Models\Turno;
use App\Models\Vendedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FecharCaixa
{
    public function __construct(private RegistrarVendaPresencial $registrarVenda) {}

    /** Turno em andamento, ou o último, para conferir o caixa. */
    public function turno(Vendedor $vendedor): ?Turno
    {
        return $vendedor->turnoAberto() ?? $vendedor->turnos()->latest('inicio')->first();
    }

    /** Totais do turno por forma de pagamento e por sabor, e o estoque do sistema (fluxo 6, passo 1). */
    public function resumo(Vendedor $vendedor): array
    {
        $turno = $this->turno($vendedor);
        $vendas = $vendedor->vendas()->where('status', 'paga')->where('turno_id', $turno?->id ?? 0);

        $porForma = (clone $vendas)->groupBy('forma_pagamento')
            ->selectRaw('forma_pagamento, sum(valor_total) as valor')->pluck('valor', 'forma_pagamento');

        $vendidos = DB::table('itens_venda')
            ->whereIn('venda_id', (clone $vendas)->select('id'))
            ->groupBy('produto_id')
            ->selectRaw('produto_id, sum(quantidade) as quantidade')
            ->pluck('quantidade', 'produto_id');

        $produtos = $vendedor->produtos()
            ->where(fn ($q) => $q->where('ativo', true)->orWhere('estoque', '!=', 0))
            ->orderBy('nome')->get();

        return [
            'turno' => $turno ? ['inicio' => $turno->inicio, 'fim' => $turno->fim] : null,
            'por_forma' => [
                'cartao' => (int) ($porForma['cartao'] ?? 0),
                'pix' => (int) ($porForma['pix'] ?? 0),
                'dinheiro' => (int) ($porForma['dinheiro'] ?? 0),
            ],
            'total' => (int) $porForma->sum(),
            'produtos' => $produtos->map(fn (Produto $p) => [
                'produto_id' => $p->id,
                'nome' => $p->nome,
                'cor' => $p->cor,
                'preco' => $p->preco,
                'estoque' => $p->estoque,
                'vendidos' => (int) ($vendidos[$p->id] ?? 0),
            ])->values(),
        ];
    }

    /**
     * Aplica a contagem física (fluxo 6, passos 3 a 5). Falta exige motivo; sobra vira "outro".
     * "Venda não registrada" cria a venda, que entra no caixa e nas métricas.
     *
     * @param  list<array{produto_id: int, contagem: int, motivo?: string|null, preco_unitario?: int|null, forma_pagamento?: string|null, metodo_cartao?: string|null, observacao?: string|null}>  $contagens
     */
    public function ajustar(Vendedor $vendedor, array $contagens): void
    {
        DB::transaction(function () use ($vendedor, $contagens) {
            $turno = $this->turno($vendedor);
            $produtos = Produto::where('vendedor_id', $vendedor->id)
                ->whereIn('id', array_column($contagens, 'produto_id'))
                ->lockForUpdate()->get()->keyBy('id');

            foreach ($contagens as $i => $c) {
                $produto = $produtos[$c['produto_id']];
                $diferenca = $c['contagem'] - $produto->estoque;
                if ($diferenca === 0) {
                    continue;
                }

                $motivo = $diferenca > 0 ? 'outro' : ($c['motivo'] ?? null);
                if (! $motivo) {
                    throw ValidationException::withMessages(["contagens.$i.motivo" => "Diga o que aconteceu com as unidades de {$produto->nome}."]);
                }

                $vendaId = null;
                if ($motivo === 'venda_nao_registrada') {
                    [$venda] = ($this->registrarVenda)($vendedor, [
                        'id_local' => (string) Str::uuid(),
                        'forma_pagamento' => $c['forma_pagamento'],
                        'metodo_cartao' => $c['metodo_cartao'] ?? null,
                        'pago_em' => now()->toIso8601String(),
                        'itens' => [['produto_id' => $produto->id, 'quantidade' => -$diferenca, 'preco_unitario' => $c['preco_unitario']]],
                    ]);
                    $vendaId = $venda->id;
                }

                $vendedor->ajustesEstoque()->create([
                    'produto_id' => $produto->id,
                    'turno_id' => $turno?->id,
                    'venda_id' => $vendaId,
                    'motivo' => $motivo,
                    'quantidade' => $diferenca,
                    'preco_padrao' => $produto->preco,
                    'observacao' => $c['observacao'] ?? null,
                ]);
                // A venda já descontou; o resto ajusta direto para a contagem.
                $produto->forceFill(['estoque' => $c['contagem']])->save();
            }
        });
    }
}
