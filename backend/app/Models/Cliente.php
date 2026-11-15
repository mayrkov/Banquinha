<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Cliente extends Authenticatable
{
    use Notifiable;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $authPasswordName = 'senha';

    protected $fillable = ['nome', 'email', 'telefone', 'senha'];

    protected $hidden = ['senha', 'remember_token'];

    protected function casts(): array
    {
        return ['senha' => 'hashed'];
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
