<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produto extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['nome', 'descricao', 'cor', 'preco', 'estoque', 'ativo'];

    protected $hidden = ['foto'];

    protected $appends = ['foto_url'];

    protected function casts(): array
    {
        return [
            'preco' => 'integer',
            'estoque' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /** Unidades bloqueadas por reservas aceitas e ainda não pagas (regra de estoque 3). */
    public static function reservadosPorProduto(int $vendedorId): array
    {
        return ItemReserva::query()
            ->join('reservas', 'reservas.id', '=', 'itens_reserva.reserva_id')
            ->where('reservas.vendedor_id', $vendedorId)
            ->where('reservas.status', 'aceito')
            ->groupBy('itens_reserva.produto_id')
            ->selectRaw('itens_reserva.produto_id, sum(itens_reserva.quantidade) as total')
            ->pluck('total', 'produto_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Endereço relativo da foto: o navegador busca /storage na mesma origem do site (proxy do Nuxt, como /api),
     * o que evita conteúdo misto quando o site está em HTTPS e a API não.
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->foto ? '/storage/'.$this->foto : null);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }
}
