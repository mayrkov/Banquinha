<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendedor extends Authenticatable
{
    use HasApiTokens, Notifiable;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'vendedores';

    protected $authPasswordName = 'senha';

    protected $fillable = [
        'nome', 'slug', 'email', 'telefone', 'senha', 'descricao',
        'chave_pix', 'nome_recebedor_pix', 'cidade_recebedor_pix',
        'provedor_cartao', 'infinitepay_handle',
        'prazo_resposta_min', 'taxa_entrega',
    ];

    protected $hidden = ['senha', 'remember_token', 'foto'];

    protected $appends = ['foto_url'];

    /** Relativo, como em Produto::fotoUrl(): o site serve /storage pela mesma origem. */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->foto ? '/storage/'.$this->foto : null);
    }

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'em_atividade' => 'boolean',
            'prazo_resposta_min' => 'integer',
            'taxa_entrega' => 'integer',
        ];
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }

    public function ajustesEstoque(): HasMany
    {
        return $this->hasMany(AjusteEstoque::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }

    public function paradas(): HasMany
    {
        return $this->hasMany(Parada::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function localizacao(): HasOne
    {
        return $this->hasOne(LocalizacaoVendedor::class);
    }

    public function turnoAberto(): ?Turno
    {
        return $this->turnos()->whereNull('fim')->latest('inicio')->first();
    }
}
