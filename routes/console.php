<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bcb:actualizar-tipo-cambio')->dailyAt('07:30')->withoutOverlapping();
Schedule::command('bcb:actualizar-tipo-cambio')->dailyAt('12:00')->withoutOverlapping();
Schedule::command('sistema:respaldar-base')->dailyAt('02:15')->withoutOverlapping();
