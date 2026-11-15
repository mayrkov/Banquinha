<?php

namespace App\Http\Controllers\Api\V1\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthClienteController extends Controller
{
    public function cadastrar(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clientes,email'],
            'telefone' => ['required', 'string', 'min:10', 'max:30'],
            'senha' => ['required', 'string', Password::min(8)],
        ]);

        $cliente = Cliente::create($dados);
        Auth::guard('cliente')->login($cliente, true);
        $request->session()->regenerate();

        return response()->json($cliente, 201);
    }

    public function logout(Request $request)
    {
        Auth::guard('cliente')->logout();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function eu(Request $request)
    {
        return response()->json($request->user('cliente'));
    }
}
