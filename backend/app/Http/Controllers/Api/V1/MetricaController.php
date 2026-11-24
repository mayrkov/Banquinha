<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MetricaController extends Controller
{
    // shortcut: fuso fixo do vendedor piloto; quando houver vendedores em outros fusos, guardar o fuso no vendedor.
    private const FUSO = 'America/Sao_Paulo';

    /**
     * Painel de métricas (D28) do dia, da semana ou do mês corrente.
     */
    public function __invoke(Request $request)
    {
        $periodo = $request->validate(['periodo' => ['sometimes', 'in:dia,semana,mes']])['periodo'] ?? 'dia';
        $vendedor = $request->user();

        $agora = Carbon::now(self::FUSO);
        $inicio = match ($periodo) {
            'dia' => $agora->copy()->startOfDay(),
            'semana' => $agora->copy()->startOfWeek(),
            'mes' => $agora->copy()->startOfMonth(),
        };
        $inicioUtc = $inicio->copy()->utc();

        $vendas = $vendedor->vendas()->where('status', 'paga')->where('pago_em', '>=', $inicioUtc);

        $itens = DB::table('itens_venda')
            ->join('vendas', 'vendas.id', '=', 'itens_venda.venda_id')
            ->join('produtos', 'produtos.id', '=', 'itens_venda.produto_id')
            ->where('vendas.vendedor_id', $vendedor->id)
            ->where('vendas.status', 'paga')
            ->where('vendas.pago_em', '>=', $inicioUtc)
            ->groupBy('produtos.id', 'produtos.nome', 'produtos.cor')
            ->selectRaw('produtos.id as produto_id, produtos.nome, produtos.cor, sum(itens_venda.quantidade) as quantidade,
                sum(itens_venda.quantidade * itens_venda.preco_padrao) as cheio,
                sum(itens_venda.quantidade * itens_venda.preco_unitario) as real')
            ->orderByDesc('quantidade')
            ->get();

        $cheio = (int) $itens->sum('cheio');
        $real = (int) $itens->sum('real');

        $porForma = (clone $vendas)->groupBy('forma_pagamento')
            ->selectRaw('forma_pagamento, sum(valor_total) as valor')->pluck('valor', 'forma_pagamento');

        // Doações e perdas saem do ajuste de estoque, valoradas pelo preço padrão (regra 3 do painel).
        $saidas = $vendedor->ajustesEstoque()->where('criado_em', '>=', $inicioUtc)->whereIn('motivo', ['doacao', 'perda'])
            ->groupBy('motivo')->selectRaw('motivo, -sum(quantidade) as quantidade, -sum(quantidade * preco_padrao) as valor')
            ->get()->keyBy('motivo');
        $saida = fn (string $m) => ['quantidade' => (int) ($saidas[$m]->quantidade ?? 0), 'valor' => (int) ($saidas[$m]->valor ?? 0)];

        Reserva::expirarVencidas();
        $reservas = $vendedor->reservas()->where('criado_em', '>=', $inicioUtc)
            ->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status');
        $conta = fn (array $status) => (int) $reservas->only($status)->sum();

        return [
            'periodo' => $periodo,
            'inicio' => $inicio->toIso8601String(),
            'faturamento_cheio' => $cheio,
            'descontos' => $cheio - $real,
            'faturamento_real' => $real,
            'taxas_entrega' => (int) (clone $vendas)->sum('taxa_entrega'),
            'por_sabor' => $itens->map(fn ($i) => [
                'produto_id' => (int) $i->produto_id,
                'nome' => $i->nome,
                'cor' => $i->cor,
                'quantidade' => (int) $i->quantidade,
                'valor' => (int) $i->real,
            ])->values(),
            'por_forma' => [
                'pix' => (int) ($porForma['pix'] ?? 0),
                'cartao' => (int) ($porForma['cartao'] ?? 0),
                'dinheiro' => (int) ($porForma['dinheiro'] ?? 0),
            ],
            'reservas' => [
                'recebidas' => (int) $reservas->sum(),
                // Cancelada pelo vendedor só acontece depois de aceitar.
                'aceitas' => $conta(['aceito', 'pago', 'entregue', 'cancelado_vendedor']),
                'negadas' => $conta(['negado']),
                'expiradas' => $conta(['expirado']),
                'entregues' => $conta(['entregue']),
            ],
            'doacoes' => $saida('doacao'),
            'perdas' => $saida('perda'),
            'estoque' => $vendedor->produtos()->where('ativo', true)->orderBy('nome')
                ->get(['id', 'nome', 'cor', 'estoque'])
                ->map(fn ($p) => ['produto_id' => $p->id, 'nome' => $p->nome, 'cor' => $p->cor, 'estoque' => $p->estoque]),
        ];
    }
}
