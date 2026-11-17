<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $reservados = Produto::reservadosPorProduto($request->user()->id);

        // reservado: unidades de reservas aceitas, para o PDV não vender o pão de quem reservou.
        return $request->user()->produtos()->orderBy('nome')->get()
            ->each(fn (Produto $p) => $p->setAttribute('reservado', $reservados[$p->id] ?? 0));
    }

    public function store(Request $request)
    {
        $dados = $request->validate($this->regras());

        return response()->json($request->user()->produtos()->create($dados), 201);
    }

    public function update(Request $request, int $produto)
    {
        $produto = $request->user()->produtos()->findOrFail($produto);
        $produto->update($request->validate($this->regras(parcial: true)));

        return $produto;
    }

    /** Envia ou troca a foto do produto. O painel já manda a imagem reduzida (quadrada, ~800 px). */
    public function foto(Request $request, int $produto)
    {
        $produto = $request->user()->produtos()->findOrFail($produto);
        $request->validate(['foto' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:3072']]);

        $antiga = $produto->foto;
        $produto->forceFill(['foto' => $request->file('foto')->store('produtos', 'public')])->save();
        if ($antiga) {
            Storage::disk('public')->delete($antiga);
        }

        return $produto;
    }

    public function removerFoto(Request $request, int $produto)
    {
        $produto = $request->user()->produtos()->findOrFail($produto);
        if ($produto->foto) {
            Storage::disk('public')->delete($produto->foto);
            $produto->forceFill(['foto' => null])->save();
        }

        return $produto;
    }

    private function regras(bool $parcial = false): array
    {
        $req = $parcial ? ['sometimes', 'required'] : ['required'];

        return [
            'nome' => [...$req, 'string', 'max:255'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'preco' => [...$req, 'integer', 'min:0'],
            'estoque' => [...$req, 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
