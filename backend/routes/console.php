<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recorrencia lancada antes da fatura fechar: uma compra recorrente no cartao
// precisa entrar no ciclo antes de virar historico.
Schedule::command('recurrences:materialize-due')->dailyAt('00:10');

// A fatura de um mes que ja passou esta fechada no mundo real, tenha alguem
// clicado ou nao. A varredura diaria mantem a base contando a mesma historia.
Schedule::command('invoices:close-due')->dailyAt('00:20');

// De manha, depois que fatura e recorrencia do dia ja rodaram — o e-mail
// reflete o estado mais atual possivel, sem chegar de madrugada.
Schedule::command('alerts:send-proactive')->dailyAt('07:00');
