<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FechamentoTest extends TestCase
{
    use RefreshDatabase;

    private Vendedor $v;

    private Produto $nutella;

    private Produto $doce;

    protected function setUp(): void
    {
        parent::setUp();
        $this->v = Vendedor::create(['nome' => 'V', 'email' => 'v@t.com', 'senha' => 'segredo123']);
        $this->nutella = $this->v->produtos()->create(['nome' => 'Nutella', 'preco' => 1500, 'estoque' => 10]);
        $this->doce = $this->v->produtos()->create(['nome' => 'Doce de leite', 'preco' => 1000, 'estoque' => 10]);
        $this->actingAs($this->v, 'vendedor')->postJson('/api/v1/vendedor/turno/iniciar')->assertOk();
    }

    private function vender(string $forma, int $produtoId, int $qtd, int $preco): void
    {
        $this->postJson('/api/v1/vendedor/vendas', [
            'id_local' => (string) Str::uuid(),
            'forma_pagamento' => $forma,
            'metodo_cartao' => $forma === 'cartao' ? 'credito' : null,
            'pago_em' => now()->toIso8601String(),
            'itens' => [['produto_id' => $produtoId, 'quantidade' => $qtd, 'preco_unitario' => $preco]],
        ])->assertCreated();
    }

    public function test_resumo_do_turno_por_forma_e_estoque(): void
    {
        $this->vender('cartao', $this->nutella->id, 2, 1500);
        $this->vender('pix', $this->doce->id, 1, 1000);

        $this->getJson('/api/v1/vendedor/fechamento')->assertOk()
            ->assertJsonPath('por_forma', ['cartao' => 3000, 'pix' => 1000, 'dinheiro' => 0])
            ->assertJsonPath('total', 4000)
            ->assertJsonPath('produtos.1.nome', 'Nutella')
            ->assertJsonPath('produtos.1.estoque', 8)
            ->assertJsonPath('produtos.1.vendidos', 2);
    }

    public function test_falta_exige_motivo(): void
    {
        $this->postJson('/api/v1/vendedor/fechamento', ['contagens' => [
            ['produto_id' => $this->nutella->id, 'contagem' => 8],
        ]])->assertUnprocessable()->assertJsonValidationErrors('contagens.0.motivo');

        $this->assertSame(10, $this->nutella->fresh()->estoque);
    }

    public function test_ajustes_viram_venda_doacao_e_aparecem_nas_metricas(): void
    {
        $this->postJson('/api/v1/vendedor/fechamento', ['contagens' => [
            // 2 vendidas sem registrar, no dinheiro, a R$ 12.
            ['produto_id' => $this->nutella->id, 'contagem' => 8, 'motivo' => 'venda_nao_registrada', 'preco_unitario' => 1200, 'forma_pagamento' => 'dinheiro'],
            // 3 doadas.
            ['produto_id' => $this->doce->id, 'contagem' => 7, 'motivo' => 'doacao'],
        ]])->assertOk()
            ->assertJsonPath('por_forma.dinheiro', 2400)
            ->assertJsonPath('produtos.0.estoque', 7)
            ->assertJsonPath('produtos.1.estoque', 8);

        $this->assertDatabaseCount('ajustes_estoque', 2);

        $this->getJson('/api/v1/vendedor/metricas?periodo=dia')->assertOk()
            ->assertJsonPath('faturamento_real', 2400)
            ->assertJsonPath('descontos', 600)
            ->assertJsonPath('doacoes', ['quantidade' => 3, 'valor' => 3000])
            ->assertJsonPath('perdas', ['quantidade' => 0, 'valor' => 0]);
    }

    public function test_sobra_vira_outro_e_sem_diferenca_nao_grava(): void
    {
        $this->postJson('/api/v1/vendedor/fechamento', ['contagens' => [
            ['produto_id' => $this->nutella->id, 'contagem' => 12, 'motivo' => 'venda_nao_registrada', 'preco_unitario' => 1, 'forma_pagamento' => 'pix'],
            ['produto_id' => $this->doce->id, 'contagem' => 10],
        ]])->assertOk();

        $this->assertDatabaseHas('ajustes_estoque', ['produto_id' => $this->nutella->id, 'motivo' => 'outro', 'quantidade' => 2, 'venda_id' => null]);
        $this->assertDatabaseCount('ajustes_estoque', 1);
        $this->assertDatabaseCount('vendas', 0);
    }

    public function test_nao_ajusta_produto_de_outro_vendedor(): void
    {
        $outro = Vendedor::create(['nome' => 'O', 'email' => 'o@t.com', 'senha' => 'segredo123']);
        $deOutro = $outro->produtos()->create(['nome' => 'X', 'preco' => 100, 'estoque' => 5]);

        $this->postJson('/api/v1/vendedor/fechamento', ['contagens' => [
            ['produto_id' => $deOutro->id, 'contagem' => 0, 'motivo' => 'perda'],
        ]])->assertUnprocessable();
        $this->assertSame(5, $deOutro->fresh()->estoque);
    }
}
