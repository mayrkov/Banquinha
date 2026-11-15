<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemReserva extends Model
{
    public $timestamps = false;

    protected $table = 'itens_reserva';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantidade' => 'integer', 'preco_unitario' => 'integer'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
