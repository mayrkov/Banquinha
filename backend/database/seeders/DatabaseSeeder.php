<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Vendedor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Vendedor piloto, cardápio inicial (D29) e um cliente de teste.
     * Somente para desenvolvimento local; pode rodar de novo (`php artisan db:seed`) sem duplicar.
     */
    public function run(): void
    {
        $vendedor = Vendedor::firstOrCreate(['email' => 'vendedor@banquinha.test'], [
            'nome' => 'Vendedor Piloto',
            'slug' => 'vendedor-piloto',
            'senha' => 'password',
            'provedor_cartao' => 'infinitepay',
        ]);

        if ($vendedor->wasRecentlyCreated) {
            $vendedor->produtos()->createMany([
                ['nome' => 'Nutella', 'cor' => '#5A3324', 'preco' => 1500, 'estoque' => 10],
                ['nome' => 'Doce de leite', 'cor' => '#9A6424', 'preco' => 1000, 'estoque' => 10],
                ['nome' => 'Goiabada', 'cor' => '#B32D46', 'preco' => 1000, 'estoque' => 10],
            ]);
        }

        Cliente::firstOrCreate(['email' => 'cliente@banquinha.test'], [
            'nome' => 'Cliente de Teste',
            'telefone' => '11999990000',
            'senha' => 'password',
        ]);
    }
}
