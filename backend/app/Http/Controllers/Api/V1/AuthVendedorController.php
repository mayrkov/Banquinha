<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthVendedorController extends Controller
{
    public function logout(Request $request)
    {
        Auth::guard('vendedor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function eu(Request $request)
    {
        return $request->user();
    }

    /** Envia ou troca a foto de perfil. O painel já manda a imagem reduzida (quadrada, ~800 px). */
    public function foto(Request $request)
    {
        $request->validate(['foto' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:3072']]);
        $vendedor = $request->user();

        $antiga = $vendedor->foto;
        $vendedor->forceFill(['foto' => $request->file('foto')->store('vendedores', 'public')])->save();
        if ($antiga) {
            Storage::disk('public')->delete($antiga);
        }

        return $vendedor;
    }

    public function removerFoto(Request $request)
    {
        $vendedor = $request->user();
        if ($vendedor->foto) {
            Storage::disk('public')->delete($vendedor->foto);
            $vendedor->forceFill(['foto' => null])->save();
        }

        return $vendedor;
    }

    public function atualizar(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('vendedores', 'slug')->ignore($request->user()->id),
            ],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'chave_pix' => ['sometimes', 'nullable', 'string', 'max:77'],
            // Limites do BR Code: nome até 25 e cidade até 15 caracteres.
            'nome_recebedor_pix' => ['sometimes', 'nullable', 'string', 'max:25'],
            'cidade_recebedor_pix' => ['sometimes', 'nullable', 'string', 'max:15'],
            'provedor_cartao' => ['sometimes', 'nullable', 'in:infinitepay,mercadopago_point,outro'],
            'infinitepay_handle' => ['sometimes', 'nullable', 'string', 'max:60'],
            'prazo_resposta_min' => ['sometimes', 'integer', 'min:1', 'max:240'],
            'taxa_entrega' => ['sometimes', 'integer', 'min:0'],
        ]);

        $vendedor = $request->user();
        $vendedor->update($dados);

        return $vendedor;
    }
}
