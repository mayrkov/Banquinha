<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\GerenciarReservas;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    public function __construct(private GerenciarReservas $reservas) {}

    /** Reservas em aberto (pendente, aceito, pago) e as encerradas nas últimas 24h. */
    public function index(Request $request)
    {
        Reserva::expirarVencidas();

        $reservas = $request->user()->reservas()
            ->where(fn ($q) => $q->whereIn('status', ['pendente', 'aceito', 'pago'])
                ->orWhere('encerrada_em', '>', now()->subDay()))
            ->oldest('criado_em')
            ->get();

        return $reservas->map(fn ($r) => ReservaResource::doVendedor($r));
    }

    public function acao(Request $request, string $codigo, string $acao)
    {
        $reserva = $request->user()->reservas()->where('codigo', $codigo)->firstOrFail();

        $reserva = match ($acao) {
            'aceitar' => $this->reservas->aceitar($reserva),
            'negar' => $this->reservas->negar($reserva),
            'cancelar' => $this->reservas->cancelarPeloVendedor($reserva),
            'confirmar-pagamento' => $this->reservas->confirmarPagamento($reserva),
            'entregar' => $this->reservas->entregar($reserva, $request->validate([
                'codigo_entrega' => ['required', 'digits:4'],
            ])['codigo_entrega']),
        };

        return ReservaResource::doVendedor($reserva);
    }
}
