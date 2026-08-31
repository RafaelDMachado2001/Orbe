<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A fatura de um mes que ja passou esta fechada no mundo real, tenha alguem
// clicado ou nao. A varredura diaria mantem a base contando a mesma historia.
Schedule::command('invoices:close-due')->dailyAt('00:20');
