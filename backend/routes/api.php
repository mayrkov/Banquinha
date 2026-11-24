<?php

use App\Http\Controllers\Api\V1\AuthVendedorController;
use App\Http\Controllers\Api\V1\Cliente\AuthClienteController;
use App\Http\Controllers\Api\V1\Cliente\ReservaClienteController;
use App\Http\Controllers\Api\V1\FechamentoController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\MetricaController;
use App\Http\Controllers\Api\V1\ParadaController;
use App\Http\Controllers\Api\V1\ProdutoController;
use App\Http\Controllers\Api\V1\Publico\VendedorPublicoController;
use App\Http\Controllers\Api\V1\ReservaController;
use App\Http\Controllers\Api\V1\TurnoController;
use App\Http\Controllers\Api\V1\VendaController;
use Illuminate\Support\Facades\Route;

// Guards de sessão separados: vendedor e cliente podem estar logados no mesmo navegador.

Route::post('v1/login', LoginController::class)->middleware('throttle:10,1');

Route::prefix('v1/vendedor')->group(function () {
    Route::middleware('auth:vendedor')->group(function () {
        Route::post('logout', [AuthVendedorController::class, 'logout']);
        Route::get('eu', [AuthVendedorController::class, 'eu']);
        Route::put('eu', [AuthVendedorController::class, 'atualizar']);
        Route::post('eu/foto', [AuthVendedorController::class, 'foto']);
        Route::delete('eu/foto', [AuthVendedorController::class, 'removerFoto']);

        Route::apiResource('produtos', ProdutoController::class)->only(['index', 'store', 'update']);
        Route::post('produtos/{produto}/foto', [ProdutoController::class, 'foto']);
        Route::delete('produtos/{produto}/foto', [ProdutoController::class, 'removerFoto']);
        Route::post('vendas', [VendaController::class, 'store']);
        Route::get('metricas', MetricaController::class);
        Route::get('fechamento', [FechamentoController::class, 'show']);
        Route::post('fechamento', [FechamentoController::class, 'store']);

        Route::post('turno/iniciar', [TurnoController::class, 'iniciar']);
        Route::post('turno/encerrar', [TurnoController::class, 'encerrar']);
        Route::put('localizacao', [TurnoController::class, 'localizacao'])->middleware('throttle:30,1');
        Route::delete('localizacao', [TurnoController::class, 'pararLocalizacao']);
        Route::apiResource('paradas', ParadaController::class)->only(['index', 'store', 'destroy']);

        Route::get('reservas', [ReservaController::class, 'index']);
        Route::post('reservas/{codigo}/{acao}', [ReservaController::class, 'acao'])
            ->whereIn('acao', ['aceitar', 'negar', 'cancelar', 'confirmar-pagamento', 'entregar']);
    });
});

Route::prefix('v1/publico')->group(function () {
    Route::get('vendedores', [VendedorPublicoController::class, 'index']);
    Route::get('vendedores/{slug}', [VendedorPublicoController::class, 'show']);
});

Route::prefix('v1/cliente')->group(function () {
    Route::post('cadastro', [AuthClienteController::class, 'cadastrar'])->middleware('throttle:10,1');

    Route::middleware('auth:cliente')->group(function () {
        Route::post('logout', [AuthClienteController::class, 'logout']);
        Route::get('eu', [AuthClienteController::class, 'eu']);

        Route::get('reservas', [ReservaClienteController::class, 'index']);
        Route::post('reservas', [ReservaClienteController::class, 'store']);
        Route::get('reservas/{codigo}', [ReservaClienteController::class, 'show']);
        Route::post('reservas/{codigo}/cancelar', [ReservaClienteController::class, 'cancelar']);
        Route::post('reservas/{codigo}/ja-paguei', [ReservaClienteController::class, 'jaPaguei']);
    });
});
