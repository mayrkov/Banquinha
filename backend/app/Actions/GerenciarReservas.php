<?php

namespace App\Actions;

use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Reserva;
use App\Models\Vendedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Regras de reserva (REGRAS_NEGOCIO.md, "Reservas"). Toda mudança de status passa por aqui.
 */
class GerenciarReservas
{
    /**
     * @param  array{tipo: string, parada_id?: int|null, endereco_entrega?: string|null, referencia_entrega?: string|null, itens: list<array{produto_id: int, quantidade: int}>}  $dados
     */
    public function criar(Cliente $cliente, Vendedor $vendedor, array $dados): Reserva
    {
        if (! $vendedor->em_atividade) {
            throw ValidationException::withMessages(['vendedor' => 'O vendedor não está em atividade no momento.']);
        }

        // Retirada só em parada que ainda vai acontecer.
        if ($dados['tipo'] === 'retirada' && ! $vendedor->paradas()->proximas()->whereKey($dados['parada_id'] ?? 0)->exists()) {
            throw ValidationException::withMessages(['parada_id' => 'Escolha uma parada programada do vendedor.']);
        }

        return DB::transaction(function () use ($cliente, $vendedor, $dados) {
            $produtos = $this->conferirDisponivel($vendedor, $dados['itens'], somenteAtivos: true);

            $entrega = $dados['tipo'] === 'entrega';
            $taxa = $entrega ? $vendedor->taxa_entrega : 0;
            $subtotal = array_sum(array_map(fn ($i) => $i['quantidade'] * $produtos[$i['produto_id']]->preco, $dados['itens']));

            $reserva = $vendedor->reservas()->create([
                'cliente_id' => $cliente->id,
                'codigo' => Str::lower(Str::random(10)),
                'tipo' => $dados['tipo'],
                'parada_id' => $entrega ? null : $dados['parada_id'],
                'endereco_entrega' => $entrega ? $dados['endereco_entrega'] : null,
                'referencia_entrega' => $entrega ? ($dados['referencia_entrega'] ?? null) : null,
                'taxa_entrega' => $taxa,
                'valor_total' => $subtotal + $taxa,
                'status' => 'pendente',
                'responder_ate' => now()->addMinutes($vendedor->prazo_resposta_min),
            ]);

            foreach ($dados['itens'] as $item) {
                $reserva->itens()->create([
                    'produto_id' => $item['produto_id'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $produtos[$item['produto_id']]->preco,
                ]);
            }

            return $reserva;
        });
    }

    public function aceitar(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva) {
            $reserva = $this->travar($reserva, 'pendente');
            if ($reserva->responder_ate->isPast()) {
                // A exceção desfaz a transação; quem marca "expirado" é Reserva::expirarVencidas().
                throw ValidationException::withMessages(['status' => 'O prazo para responder esta reserva acabou.']);
            }
            // Revalida: entre a criação e o aceite pode ter havido vendas e outros aceites.
            $this->conferirDisponivel($reserva->vendedor, $reserva->itens->map->only('produto_id', 'quantidade')->all());
            $reserva->update(['status' => 'aceito']);

            return $reserva;
        });
    }

    public function negar(Reserva $reserva): Reserva
    {
        return $this->mudar($reserva, 'pendente', ['status' => 'negado', 'encerrada_em' => now()]);
    }

    public function cancelarPeloCliente(Reserva $reserva): Reserva
    {
        return $this->mudar($reserva, 'pendente', ['status' => 'cancelado_cliente', 'encerrada_em' => now()]);
    }

    public function cancelarPeloVendedor(Reserva $reserva): Reserva
    {
        return $this->mudar($reserva, 'aceito', ['status' => 'cancelado_vendedor', 'encerrada_em' => now()]);
    }

    public function informarPagamento(Reserva $reserva): Reserva
    {
        return $this->mudar($reserva, 'aceito', ['cliente_informou_pagamento_em' => now()]);
    }

    /** Vendedor conferiu o Pix: registra a venda, desconta o estoque e gera o código de entrega. */
    public function confirmarPagamento(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva) {
            $reserva = $this->travar($reserva, 'aceito');
            $vendedor = $reserva->vendedor;
            $produtos = Produto::whereIn('id', $reserva->itens->pluck('produto_id'))->lockForUpdate()->get()->keyBy('id');

            $venda = $vendedor->vendas()->create([
                'origem' => 'reserva',
                'reserva_id' => $reserva->id,
                'turno_id' => $vendedor->turnoAberto()?->id,
                'forma_pagamento' => 'pix',
                'taxa_entrega' => $reserva->taxa_entrega,
                'valor_total' => $reserva->valor_total,
                'status' => 'paga',
                'pago_em' => now(),
            ]);
            foreach ($reserva->itens as $item) {
                $produto = $produtos[$item->produto_id];
                $venda->itens()->create([
                    'produto_id' => $produto->id,
                    'quantidade' => $item->quantidade,
                    'preco_padrao' => $produto->preco,
                    'preco_unitario' => $item->preco_unitario,
                ]);
                $produto->decrement('estoque', $item->quantidade);
            }
            $venda->pagamentos()->create([
                'provedor' => 'pix_estatico',
                'metodo' => 'pix',
                'valor' => $reserva->valor_total,
                'status' => 'aprovado',
                'pago_em' => now(),
            ]);

            $reserva->update([
                'status' => 'pago',
                'codigo_entrega' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            ]);

            return $reserva;
        });
    }

    public function entregar(Reserva $reserva, string $codigo): Reserva
    {
        return DB::transaction(function () use ($reserva, $codigo) {
            $reserva = $this->travar($reserva, 'pago');
            if (! hash_equals((string) $reserva->codigo_entrega, $codigo)) {
                throw ValidationException::withMessages(['codigo_entrega' => 'Código incorreto.']);
            }
            $reserva->update(['status' => 'entregue', 'encerrada_em' => now()]);

            return $reserva;
        });
    }

    /**
     * Confere disponível = estoque − reservas aceitas, por produto. Bloqueia as linhas dos produtos até o fim da transação.
     *
     * @param  list<array{produto_id: int, quantidade: int}>  $itens
     * @return array<int, Produto>
     */
    private function conferirDisponivel(Vendedor $vendedor, array $itens, bool $somenteAtivos = false): array
    {
        $produtos = $vendedor->produtos()
            ->whereIn('id', array_column($itens, 'produto_id'))
            ->when($somenteAtivos, fn ($q) => $q->where('ativo', true))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $reservados = Produto::reservadosPorProduto($vendedor->id);

        foreach ($itens as $i => $item) {
            $produto = $produtos[$item['produto_id']] ?? null;
            if (! $produto) {
                throw ValidationException::withMessages(["itens.$i.produto_id" => 'Produto indisponível.']);
            }
            $disponivel = $produto->estoque - ($reservados[$produto->id] ?? 0);
            if ($item['quantidade'] > $disponivel) {
                throw ValidationException::withMessages([
                    "itens.$i.quantidade" => "{$produto->nome}: só ".max($disponivel, 0).' disponível(is).',
                ]);
            }
        }

        return $produtos->all();
    }

    private function mudar(Reserva $reserva, string $statusEsperado, array $campos): Reserva
    {
        return DB::transaction(function () use ($reserva, $statusEsperado, $campos) {
            $reserva = $this->travar($reserva, $statusEsperado);
            $reserva->update($campos);

            return $reserva;
        });
    }

    private function travar(Reserva $reserva, string $statusEsperado): Reserva
    {
        $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
        if ($reserva->status !== $statusEsperado) {
            throw ValidationException::withMessages(['status' => 'Esta reserva mudou de situação. Atualize a tela.']);
        }

        return $reserva;
    }
}
