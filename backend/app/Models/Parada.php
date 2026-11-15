<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Parada extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = null;

    protected $fillable = ['local', 'latitude', 'longitude', 'inicio', 'fim', 'ativo'];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'ativo' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** Ativas e ainda não terminadas (sem fim: vale até o fim do dia do início). */
    public function scopeProximas(Builder $q): Builder
    {
        return $q->where('ativo', true)
            ->where(fn ($q) => $q->where('fim', '>', now())
                ->orWhere(fn ($q) => $q->whereNull('fim')->where('inicio', '>', now()->startOfDay())))
            ->orderBy('inicio');
    }
}
