<?php

namespace App\Http\Controllers\Api\V1\Cliente;

use App\Actions\GerenciarReservas;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Models\Vendedor;
use Illuminate\Http\Request;

class ReservaClienteController extends Controller
{
    public function __construct(private GerenciarReservas $reservas) {}

    public function index(Request $request)
    {
        Reserva::expirarVencidas();

        return ReservaResource::collection(
            $request->user('cliente')->reservas()->latest('criado_em')->limit(50)->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate(['vendedor' => ['required', 'string']]);
        $vendedor = Vendedor::where('slug', $request->string('vendedor'))->firstOrFail();

        $dados = $request->validate([
            'tipo' => ['required', 'in:entrega,retirada'],
            'endereco_entrega' => ['required_if:tipo,entrega', 'nullable', 'string', 'max:500'],
            'referencia_entrega' => ['nullable', 'string', 'max:255'],
            'parada_id' => ['required_if:tipo,retirada', 'nullable', 'integer'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer', 'distinct'],
            'itens.*.quantidade' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $reserva = $this->reservas->criar($request->user('cliente'), $vendedor, $dados);

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $codigo)
    {
        Reserva::expirarVencidas();

        return new ReservaResource($this->minha($request, $codigo));
    }

    public function cancelar(Request $request, string $codigo)
    {
        return new ReservaResource($this->reservas->cancelarPeloCliente($this->minha($request, $codigo)));
    }

    public function jaPaguei(Request $request, string $codigo)
    {
        return new ReservaResource($this->reservas->informarPagamento($this->minha($request, $codigo)));
    }

    private function minha(Request $request, string $codigo): Reserva
    {
        return $request->user('cliente')->reservas()->where('codigo', $codigo)->firstOrFail();
    }
}
