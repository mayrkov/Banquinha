<?php

namespace Tests\Feature;

use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricaTest extends TestCase
{
    use RefreshDatabase;

    public function test_exige_login(): void
    {
        $this->getJson('/api/v1/vendedor/metricas')->assertUnauthorized();
    }

    public function test_soma_faturamento_descontos_sabor_e_forma_do_periodo(): void
    {
        Carbon::setTestNow('2026-11-24 15:00:00'); // 12:00 em São Paulo
        $v = Vendedor::create(['nome' => 'V', 'email' => 'v@t.com', 'senha' => 'segredo123']);
        $outro = Vendedor::create(['nome' => 'O', 'email' => 'o@t.com', 'senha' => 'segredo123']);
        $nutella = $v->produtos()->create(['nome' => 'Nutella', 'preco' => 1500, 'estoque' => 20]);
        $doce = $v->produtos()->create(['nome' => 'Doce de leite', 'preco' => 1000, 'estoque' => 20]);
        $deOutro = $outro->produtos()->create(['nome' => 'X', 'preco' => 1000, 'estoque' => 20]);

        $vender = fn (Vendedor $quem, string $forma, string $pagoEm, array $itens) => $this->actingAs($quem, 'vendedor')
            ->postJson('/api/v1/vendedor/vendas', [
                'id_local' => (string) Str::uuid(),
                'forma_pagamento' => $forma,
                'metodo_cartao' => $forma === 'cartao' ? 'debito' : null,
                'pago_em' => $pagoEm,
                'itens' => $itens,
            ])->assertCreated();

        // Hoje: 2 Nutella com desconto (R$ 13) no Pix e 1 doce no dinheiro.
        $vender($v, 'pix', '2026-11-24T14:00:00Z', [['produto_id' => $nutella->id, 'quantidade' => 2, 'preco_unitario' => 1300]]);
        $vender($v, 'dinheiro', '2026-11-24T14:30:00Z', [['produto_id' => $doce->id, 'quantidade' => 1, 'preco_unitario' => 1000]]);
        // Ontem à noite em São Paulo (já é dia 24 em UTC): fica fora do dia, dentro da semana.
        $vender($v, 'cartao', '2026-11-24T02:00:00Z', [['produto_id' => $nutella->id, 'quantidade' => 1, 'preco_unitario' => 1500]]);
        // Venda de outro vendedor não entra.
        $vender($outro, 'pix', '2026-11-24T14:00:00Z', [['produto_id' => $deOutro->id, 'quantidade' => 5, 'preco_unitario' => 1000]]);

        $this->actingAs($v, 'vendedor')->getJson('/api/v1/vendedor/metricas?periodo=dia')->assertOk()
            ->assertJsonPath('faturamento_cheio', 4000)
            ->assertJsonPath('descontos', 400)
            ->assertJsonPath('faturamento_real', 3600)
            ->assertJsonPath('por_forma', ['pix' => 2600, 'cartao' => 0, 'dinheiro' => 1000])
            ->assertJsonPath('por_sabor.0.nome', 'Nutella')
            ->assertJsonPath('por_sabor.0.quantidade', 2)
            ->assertJsonCount(2, 'por_sabor')
            ->assertJsonCount(2, 'estoque');

        $this->getJson('/api/v1/vendedor/metricas?periodo=semana')->assertOk()
            ->assertJsonPath('faturamento_real', 5100)
            ->assertJsonPath('por_forma.cartao', 1500);

        $this->getJson('/api/v1/vendedor/metricas?periodo=ano')->assertUnprocessable();
        Carbon::setTestNow();
    }
}
