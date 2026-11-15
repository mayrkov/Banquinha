<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaFluxoTest extends TestCase
{
    use RefreshDatabase;

    private Vendedor $vendedor;

    private Cliente $cliente;

    private Produto $nutella;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendedor = Vendedor::create([
            'nome' => 'V', 'slug' => 'v', 'email' => 'v@t.com', 'senha' => 'segredo123',
            'chave_pix' => 'pix@v.com', 'nome_recebedor_pix' => 'V', 'cidade_recebedor_pix' => 'Cidade',
            'taxa_entrega' => 300,
        ]);
        $this->vendedor->forceFill(['em_atividade' => true])->save();
        $this->nutella = $this->vendedor->produtos()->create(['nome' => 'Nutella', 'preco' => 1500, 'estoque' => 5]);
        $this->cliente = Cliente::create(['nome' => 'C', 'email' => 'c@t.com', 'telefone' => '11999990000', 'senha' => 'segredo123']);
    }

    private function reservar(int $qtd, array $extra = [])
    {
        return $this->actingAs($this->cliente, 'cliente')->postJson('/api/v1/cliente/reservas', $extra + [
            'vendedor' => 'v',
            'tipo' => 'entrega',
            'endereco_entrega' => 'Rua A, 1',
            'itens' => [['produto_id' => $this->nutella->id, 'quantidade' => $qtd]],
        ]);
    }

    private function vendedorFaz(string $codigo, string $acao, array $corpo = [])
    {
        return $this->actingAs($this->vendedor, 'vendedor')->postJson("/api/v1/vendedor/reservas/$codigo/$acao", $corpo);
    }

    public function test_fluxo_completo_entrega(): void
    {
        $codigo = $this->reservar(2)->assertCreated()
            ->assertJsonPath('status', 'pendente')
            ->assertJsonPath('valor_total', 3300)
            ->assertJsonPath('pix', null)
            ->json('codigo');

        $this->vendedorFaz($codigo, 'aceitar')->assertOk()->assertJsonPath('status', 'aceito');

        // Aceita bloqueia: disponível público cai, estoque físico não.
        $this->getJson('/api/v1/publico/vendedores/v')->assertJsonPath('produtos.0.disponivel', 3);
        $this->assertSame(5, $this->nutella->fresh()->estoque);

        $this->actingAs($this->cliente, 'cliente')->getJson("/api/v1/cliente/reservas/$codigo")
            ->assertJsonPath('pix.chave', 'pix@v.com');
        $this->actingAs($this->cliente, 'cliente')->postJson("/api/v1/cliente/reservas/$codigo/ja-paguei")->assertOk();

        $this->vendedorFaz($codigo, 'confirmar-pagamento')->assertOk()
            ->assertJsonPath('status', 'pago')
            ->assertJsonMissingPath('codigo_entrega');
        $this->assertSame(3, $this->nutella->fresh()->estoque);
        $this->assertSame(1, $this->vendedor->vendas()->where('origem', 'reserva')->count());

        $entrega = $this->actingAs($this->cliente, 'cliente')->getJson("/api/v1/cliente/reservas/$codigo")->json('codigo_entrega');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $entrega);

        $errado = $entrega === '0000' ? '1111' : '0000';
        $this->vendedorFaz($codigo, 'entregar', ['codigo_entrega' => $errado])->assertUnprocessable();
        $this->vendedorFaz($codigo, 'entregar', ['codigo_entrega' => $entrega])->assertOk()->assertJsonPath('status', 'entregue');
    }

    public function test_limite_ao_disponivel_e_revalidacao_no_aceite(): void
    {
        $this->reservar(6)->assertUnprocessable()->assertJsonValidationErrors('itens.0.quantidade');

        $a = $this->reservar(4)->json('codigo');
        $b = $this->reservar(4)->json('codigo'); // pendentes não bloqueiam
        $this->vendedorFaz($a, 'aceitar')->assertOk();
        $this->vendedorFaz($b, 'aceitar')->assertUnprocessable();
    }

    public function test_cancelamento_pelo_cliente_so_pendente(): void
    {
        $codigo = $this->reservar(1)->json('codigo');
        $this->vendedorFaz($codigo, 'aceitar');
        $this->actingAs($this->cliente, 'cliente')->postJson("/api/v1/cliente/reservas/$codigo/cancelar")->assertUnprocessable();

        $outra = $this->reservar(1)->json('codigo');
        $this->actingAs($this->cliente, 'cliente')->postJson("/api/v1/cliente/reservas/$outra/cancelar")
            ->assertOk()->assertJsonPath('status', 'cancelado_cliente');
    }

    public function test_expira_sem_resposta_e_nao_aceita_depois(): void
    {
        $codigo = $this->reservar(1)->json('codigo');
        $this->travel(16)->minutes();
        $this->vendedorFaz($codigo, 'aceitar')->assertUnprocessable();
        $this->actingAs($this->cliente, 'cliente')->getJson("/api/v1/cliente/reservas/$codigo")->assertJsonPath('status', 'expirado');
    }

    public function test_retirada_exige_parada_do_vendedor_e_fora_de_atividade_bloqueia(): void
    {
        $parada = $this->vendedor->paradas()->create(['local' => 'Praça', 'inicio' => now()->addHour()]);
        $passada = $this->vendedor->paradas()->create(['local' => 'Ontem', 'inicio' => now()->subDays(2), 'fim' => now()->subDay()]);
        $this->reservar(1, ['tipo' => 'retirada', 'parada_id' => 999])->assertUnprocessable();
        $this->reservar(1, ['tipo' => 'retirada', 'parada_id' => $passada->id])->assertUnprocessable()->assertJsonValidationErrors('parada_id');
        $this->reservar(1, ['vendedor' => ['x']])->assertUnprocessable();
        $this->reservar(1, ['tipo' => 'retirada', 'parada_id' => $parada->id])->assertCreated()->assertJsonPath('valor_total', 1500);

        $this->actingAs($this->vendedor, 'vendedor')->postJson('/api/v1/vendedor/turno/encerrar')->assertJsonPath('em_atividade', false);
        $this->reservar(1)->assertUnprocessable();
        $this->getJson('/api/v1/publico/vendedores/v')->assertJsonPath('status', 'fora_de_atividade');
    }

    public function test_cliente_nao_ve_reserva_de_outro_e_vendedor_nao_ve_codigo(): void
    {
        $codigo = $this->reservar(1)->json('codigo');
        $outro = Cliente::create(['nome' => 'X', 'email' => 'x@t.com', 'telefone' => '11999990001', 'senha' => 'segredo123']);
        $this->actingAs($outro, 'cliente')->getJson("/api/v1/cliente/reservas/$codigo")->assertNotFound();
        $this->actingAs($this->vendedor, 'vendedor')->getJson('/api/v1/vendedor/reservas')
            ->assertJsonPath('0.cliente.telefone', '11999990000');
    }

    public function test_cadastro_de_cliente(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/'])
            ->postJson('/api/v1/cliente/cadastro', ['nome' => 'N', 'email' => 'n@t.com', 'telefone' => '11988887777', 'senha' => 'segredo123'])
            ->assertCreated()->assertJsonMissingPath('senha');
        $this->getJson('/api/v1/cliente/eu')->assertOk()->assertJsonPath('email', 'n@t.com');
        $this->postJson('/api/v1/cliente/logout')->assertNoContent();
        $this->getJson('/api/v1/cliente/reservas')->assertUnauthorized();

        // Login único reconhece o cliente.
        $this->postJson('/api/v1/login', ['email' => 'n@t.com', 'senha' => 'segredo123'])
            ->assertOk()->assertJsonPath('tipo', 'cliente');
        $this->getJson('/api/v1/cliente/reservas')->assertOk();
        $this->getJson('/api/v1/vendedor/eu')->assertUnauthorized();
    }
}
