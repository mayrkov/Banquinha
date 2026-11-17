<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendedorPainelTest extends TestCase
{
    use RefreshDatabase;

    private function vendedor(string $email = 'v@teste.com'): Vendedor
    {
        return Vendedor::create(['nome' => 'V', 'email' => $email, 'senha' => 'segredo123']);
    }

    private function produto(Vendedor $v, array $attrs = []): Produto
    {
        return $v->produtos()->create($attrs + ['nome' => 'Nutella', 'preco' => 1500, 'estoque' => 10]);
    }

    public function test_login_por_cookie_e_rota_protegida(): void
    {
        $this->vendedor();
        $spa = ['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/'];

        $this->withHeaders($spa)->postJson('/api/v1/login', ['email' => 'v@teste.com', 'senha' => 'errada'])
            ->assertUnprocessable();

        $this->withHeaders($spa)->postJson('/api/v1/login', ['email' => 'v@teste.com', 'senha' => 'segredo123'])
            ->assertOk()->assertJsonPath('tipo', 'vendedor')
            ->assertJsonPath('usuario.email', 'v@teste.com')->assertJsonMissingPath('usuario.senha');

        $this->withHeaders($spa)->getJson('/api/v1/vendedor/eu')->assertOk();
    }

    public function test_rotas_exigem_login(): void
    {
        $this->getJson('/api/v1/vendedor/produtos')->assertUnauthorized();
        $this->postJson('/api/v1/vendedor/vendas', [])->assertUnauthorized();
    }

    public function test_crud_de_produtos_isolado_por_vendedor(): void
    {
        $outro = $this->vendedor('outro@teste.com');
        $doOutro = $this->produto($outro);
        $this->actingAs($eu = $this->vendedor(), 'vendedor');

        $id = $this->postJson('/api/v1/vendedor/produtos', ['nome' => 'Goiabada', 'preco' => 1000, 'estoque' => 5])
            ->assertCreated()->json('id');

        $this->putJson("/api/v1/vendedor/produtos/$id", ['ativo' => false, 'preco' => 900])
            ->assertOk()->assertJsonPath('ativo', false)->assertJsonPath('preco', 900);

        $this->getJson('/api/v1/vendedor/produtos')->assertOk()->assertJsonCount(1);
        $this->putJson("/api/v1/vendedor/produtos/{$doOutro->id}", ['preco' => 1])->assertNotFound();
    }

    public function test_foto_do_produto_troca_remove_e_aparece_na_pagina_publica(): void
    {
        Storage::fake('public');
        $outro = $this->produto($this->vendedor('outro@teste.com'));
        $this->actingAs($eu = $this->vendedor(), 'vendedor');
        $eu->forceFill(['slug' => 'eu', 'em_atividade' => true])->save();
        $p = $this->produto($eu);

        $this->post("/api/v1/vendedor/produtos/{$p->id}/foto", ['foto' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->post("/api/v1/vendedor/produtos/{$outro->id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertNotFound();

        $primeira = $this->post("/api/v1/vendedor/produtos/{$p->id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg', 800, 800)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonMissingPath('foto')->json('foto_url');
        $this->assertStringStartsWith('/storage/produtos/', $primeira);

        $segunda = $this->post("/api/v1/vendedor/produtos/{$p->id}/foto", ['foto' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])
            ->assertOk()->json('foto_url');
        Storage::disk('public')->assertMissing(substr($primeira, 9));
        Storage::disk('public')->assertExists(substr($segunda, 9));
        $this->getJson('/api/v1/publico/vendedores/eu')->assertJsonPath('produtos.0.foto_url', $segunda);

        $this->deleteJson("/api/v1/vendedor/produtos/{$p->id}/foto")->assertOk()->assertJsonPath('foto_url', null);
        Storage::disk('public')->assertMissing(substr($segunda, 9));
    }

    public function test_foto_de_perfil_aparece_no_mapa_e_na_pagina(): void
    {
        Storage::fake('public');
        $this->actingAs($eu = $this->vendedor(), 'vendedor');
        $eu->forceFill(['slug' => 'eu', 'em_atividade' => true])->save();

        $this->post('/api/v1/vendedor/eu/foto', ['foto' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $primeira = $this->post('/api/v1/vendedor/eu/foto', ['foto' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonMissingPath('foto')->json('foto_url');
        $segunda = $this->post('/api/v1/vendedor/eu/foto', ['foto' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->json('foto_url');
        Storage::disk('public')->assertMissing(substr($primeira, 9));
        $this->assertStringStartsWith('/storage/vendedores/', $segunda);

        $this->getJson('/api/v1/publico/vendedores')->assertJsonPath('0.foto_url', $segunda);
        $this->getJson('/api/v1/publico/vendedores/eu')->assertJsonPath('foto_url', $segunda);

        $this->deleteJson('/api/v1/vendedor/eu/foto')->assertOk()->assertJsonPath('foto_url', null);
        Storage::disk('public')->assertMissing(substr($segunda, 9));
    }

    public function test_venda_presencial_desconta_estoque_e_e_idempotente(): void
    {
        $this->actingAs($v = $this->vendedor(), 'vendedor');
        $v->update(['provedor_cartao' => 'infinitepay']);
        $nutella = $this->produto($v);
        $goiabada = $this->produto($v, ['nome' => 'Goiabada', 'preco' => 1000]);

        $payload = [
            'id_local' => (string) Str::uuid(),
            'forma_pagamento' => 'cartao',
            'metodo_cartao' => 'debito',
            'pago_em' => now()->subHour()->toIso8601String(),
            'itens' => [
                ['produto_id' => $nutella->id, 'quantidade' => 2, 'preco_unitario' => 1500],
                ['produto_id' => $goiabada->id, 'quantidade' => 1, 'preco_unitario' => 800],
            ],
        ];

        $this->postJson('/api/v1/vendedor/vendas', $payload)
            ->assertCreated()
            ->assertJsonPath('valor_total', 3800)
            ->assertJsonPath('status', 'paga')
            ->assertJsonPath('pagamentos.0.provedor', 'infinitetap')
            ->assertJsonPath('pagamentos.0.metodo', 'debito')
            ->assertJsonPath('itens.1.preco_padrao', 1000);

        // Reenvio da fila offline: mesma venda, sem descontar de novo.
        $this->postJson('/api/v1/vendedor/vendas', $payload)->assertOk();

        $this->assertSame(8, $nutella->fresh()->estoque);
        $this->assertSame(9, $goiabada->fresh()->estoque);
        $this->assertSame(1, $v->vendas()->count());
    }

    public function test_venda_rejeita_produto_de_outro_vendedor(): void
    {
        $doOutro = $this->produto($this->vendedor('outro@teste.com'));
        $this->actingAs($this->vendedor(), 'vendedor');

        $this->postJson('/api/v1/vendedor/vendas', [
            'id_local' => (string) Str::uuid(),
            'forma_pagamento' => 'pix',
            'pago_em' => now()->toIso8601String(),
            'itens' => [['produto_id' => $doOutro->id, 'quantidade' => 1, 'preco_unitario' => 1500]],
        ])->assertUnprocessable()->assertJsonValidationErrors('itens.0.produto_id');

        $this->assertSame(10, $doOutro->fresh()->estoque);
    }
}
