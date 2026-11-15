<?php

use App\Models\Reserva;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// D22: pendentes sem resposta no prazo expiram.
Schedule::call(fn () => Reserva::expirarVencidas())->everyMinute()->name('reservas:expirar');

// LGPD: endereço de entrega apagado 3 dias após o encerramento da reserva.
Schedule::call(fn () => Reserva::whereNotNull('endereco_entrega')
    ->where('encerrada_em', '<', now()->subDays(3))
    ->update(['endereco_entrega' => null, 'referencia_entrega' => null, 'endereco_apagado_em' => now()])
)->hourly()->name('reservas:apagar-enderecos');
