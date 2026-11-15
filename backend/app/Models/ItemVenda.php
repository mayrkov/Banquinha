<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemVenda extends Model
{
    public $timestamps = false;

    protected $table = 'itens_venda';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'preco_padrao' => 'integer',
            'preco_unitario' => 'integer',
        ];
    }
}
