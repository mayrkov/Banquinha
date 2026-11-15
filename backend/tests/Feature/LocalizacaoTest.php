<?php

namespace Tests\Feature;

use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_localizacao_so_no_turno_some_ao_encerrar_e_expira(): void
    {
        $v = Vendedor::create(['nome' => 'V', 'slug' => 'v', 'email' => 'v@t.com', 'senha' => 'segredo123']);
        $v->produtos()->create(['nome' => 'Nutella', 'cor' => '#5A3324', 'preco' => 1500, 'estoque' => 2]);
        $this->actingAs($v, 'vendedor');
        $pos = ['latitude' => -23.55, 'longitude' => -46.63];

        $this->putJson('/api/v1/vendedor/localizacao', $pos)->assertUnprocessable();

        $this->postJson('/api/v1/vendedor/turno/iniciar');
        $this->putJson('/api/v1/vendedor/localizacao', $pos)->assertNoContent();
        $this->putJson('/api/v1/vendedor/localizacao', ['latitude' => 91, 'longitude' => 0])->assertUnprocessable();

        $this->getJson('/api/v1/publico/vendedores')
            ->assertJsonPath('0.localizacao.latitude', -23.55)
            ->assertJsonPath('0.cores.0', '#5A3324')
            ->assertJsonPath('0.preco_min', 1500)
            ->assertJsonPath('0.status', 'vendendo');
        $this->getJson('/api/v1/publico/vendedores/v')->assertJsonPath('localizacao.longitude', -46.63);

        // Sem atualização por mais de 2h: some do mapa.
        $this->travel(121)->minutes();
        $this->getJson('/api/v1/publico/vendedores')->assertJsonPath('0.localizacao', null);
        $this->travelBack();

        $this->putJson('/api/v1/vendedor/localizacao', $pos)->assertNoContent();
        $this->postJson('/api/v1/vendedor/turno/encerrar');
        $this->assertNull($v->localizacao()->first());
        $this->getJson('/api/v1/publico/vendedores')->assertJsonCount(0);
    }
}
