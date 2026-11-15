<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ParadaController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->paradas()->proximas()->get();
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'local' => ['required', 'string', 'max:255'],
            'inicio' => ['required', 'date'],
            'fim' => ['nullable', 'date', 'after:inicio'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return response()->json($request->user()->paradas()->create($dados), 201);
    }

    /** Desativa (não apaga: reservas de retirada apontam para a parada). */
    public function destroy(Request $request, int $parada)
    {
        $request->user()->paradas()->findOrFail($parada)->update(['ativo' => false]);

        return response()->noContent();
    }
}
