<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Models\Produto;
use App\Models\Vendedor;

class VendedorPublicoController extends Controller
{
    /** Vendedores em atividade (D17), com posição, cores e faixa de preço para o mapa. */
    public function index()
    {
        $vendedores = Vendedor::where('em_atividade', true)->whereNotNull('slug')->orderBy('nome')
            ->with(['localizacao', 'produtos' => fn ($q) => $q->where('ativo', true)->orderBy('nome')])
            ->get();

        return $vendedores->map(function (Vendedor $v) {
            $reservados = Produto::reservadosPorProduto($v->id);
            $disponivel = $v->produtos->sum(fn (Produto $p) => max(0, $p->estoque - ($reservados[$p->id] ?? 0)));

            return [
                'nome' => $v->nome,
                'slug' => $v->slug,
                'descricao' => $v->descricao,
                'status' => $disponivel > 0 ? 'vendendo' : 'esgotado',
                'cores' => $v->produtos->map(fn (Produto $p) => $p->cor)->values(),
                'preco_min' => $v->produtos->min('preco'),
                'preco_max' => $v->produtos->max('preco'),
                'localizacao' => $v->localizacao?->paraPublico(),
            ];
        });
    }

    /** Página pública: status, cardápio com disponível real e próximas paradas. */
    public function show(string $slug)
    {
        $vendedor = Vendedor::where('slug', $slug)->firstOrFail();
        $reservados = Produto::reservadosPorProduto($vendedor->id);

        $produtos = $vendedor->produtos()->where('ativo', true)->orderBy('nome')->get()
            ->map(fn (Produto $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'descricao' => $p->descricao,
                'cor' => $p->cor,
                'preco' => $p->preco,
                'disponivel' => max(0, $p->estoque - ($reservados[$p->id] ?? 0)),
            ]);

        $status = ! $vendedor->em_atividade ? 'fora_de_atividade'
            : ($produtos->sum('disponivel') > 0 ? 'vendendo' : 'esgotado');

        return [
            'nome' => $vendedor->nome,
            'slug' => $vendedor->slug,
            'descricao' => $vendedor->descricao,
            'status' => $status,
            'taxa_entrega' => $vendedor->taxa_entrega,
            'prazo_resposta_min' => $vendedor->prazo_resposta_min,
            'produtos' => $vendedor->em_atividade ? $produtos : [],
            'paradas' => $vendedor->paradas()->proximas()->limit(5)->get(['id', 'local', 'latitude', 'longitude', 'inicio', 'fim']),
            'localizacao' => $vendedor->em_atividade ? $vendedor->localizacao?->paraPublico() : null,
            'atualizado_em' => now(),
        ];
    }
}
