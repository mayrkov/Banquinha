<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TurnoController extends Controller
{
    public function iniciar(Request $request)
    {
        $vendedor = $request->user();
        DB::transaction(function () use ($vendedor) {
            if (! $vendedor->turnoAberto()) {
                $vendedor->turnos()->create(['inicio' => now()]);
            }
            $vendedor->forceFill(['em_atividade' => true])->save();
        });

        return $vendedor->fresh();
    }

    /** Posição atual, enviada pelo painel aberto durante o turno. */
    public function localizacao(Request $request)
    {
        $dados = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $vendedor = $request->user();
        if (! $vendedor->em_atividade) {
            throw ValidationException::withMessages(['turno' => 'Inicie o turno para compartilhar a localização.']);
        }

        $vendedor->localizacao()->updateOrCreate([], $dados + ['atualizado_em' => now()]);

        return response()->noContent();
    }

    /** Para de mostrar a posição sem encerrar o turno. */
    public function pararLocalizacao(Request $request)
    {
        $request->user()->localizacao()->delete();

        return response()->noContent();
    }

    public function encerrar(Request $request)
    {
        $vendedor = $request->user();
        DB::transaction(function () use ($vendedor) {
            $vendedor->turnos()->whereNull('fim')->update(['fim' => now()]);
            // Fora do turno não guarda posição: nunca expor onde o vendedor mora.
            $vendedor->localizacao()->delete();
            $vendedor->forceFill(['em_atividade' => false])->save();
        });

        return $vendedor->fresh();
    }
}
