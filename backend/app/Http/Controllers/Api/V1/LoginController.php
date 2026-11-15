<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Login único: identifica pelo e-mail e senha se é vendedor ou cliente.
     * Vendedor é conferido primeiro (o mesmo e-mail pode existir nas duas tabelas).
     */
    public function __invoke(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);
        $credenciais = ['email' => $dados['email'], 'password' => $dados['senha']];

        foreach (['vendedor', 'cliente'] as $tipo) {
            // remember: fica logado no celular entre os acessos.
            if (Auth::guard($tipo)->attempt($credenciais, true)) {
                $request->session()->regenerate();

                return response()->json(['tipo' => $tipo, 'usuario' => Auth::guard($tipo)->user()]);
            }
        }

        throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
    }
}
