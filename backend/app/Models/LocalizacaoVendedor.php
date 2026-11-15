<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalizacaoVendedor extends Model
{
    /** Sem atualização por mais que isso, a posição deixa de ser mostrada (REGRAS: localização 4). */
    const VALIDADE_HORAS = 2;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'localizacao_vendedor';

    protected $primaryKey = 'vendedor_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'atualizado_em' => 'datetime'];
    }

    public function valida(): bool
    {
        return $this->atualizado_em->gt(now()->subHours(self::VALIDADE_HORAS));
    }

    public function paraPublico(): ?array
    {
        return $this->valida() ? $this->only('latitude', 'longitude', 'atualizado_em') : null;
    }
}
