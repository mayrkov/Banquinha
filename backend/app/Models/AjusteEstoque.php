<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteEstoque extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = null;

    protected $table = 'ajustes_estoque';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantidade' => 'integer', 'preco_padrao' => 'integer'];
    }
}
