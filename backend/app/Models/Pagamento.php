<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pagamento extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valor' => 'integer',
            'dados_retorno' => 'array',
            'pago_em' => 'datetime',
        ];
    }
}
