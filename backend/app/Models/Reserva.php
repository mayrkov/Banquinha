<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reserva extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'taxa_entrega' => 'integer',
            'valor_total' => 'integer',
            'responder_ate' => 'datetime',
            'cliente_informou_pagamento_em' => 'datetime',
            'encerrada_em' => 'datetime',
            'endereco_apagado_em' => 'datetime',
        ];
    }

    /** Pendentes sem resposta no prazo viram "expirado" (D22). Chamado pelo agendador e antes das listagens. */
    public static function expirarVencidas(): int
    {
        return static::where('status', 'pendente')
            ->where('responder_ate', '<', now())
            ->update(['status' => 'expirado', 'encerrada_em' => now(), 'atualizado_em' => now()]);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemReserva::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function parada(): BelongsTo
    {
        return $this->belongsTo(Parada::class);
    }
}
