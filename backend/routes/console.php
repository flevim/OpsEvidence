<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Planificacion de OpsEvidence
|--------------------------------------------------------------------------
| El scheduler encola los checks vencidos y recalcula agregados.
| El trabajo real ocurre en el worker de colas.
*/

Schedule::command('opsevidence:dispatch-checks')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('opsevidence:compute-daily-summaries')
    ->dailyAt('01:15')
    ->withoutOverlapping(30);

Schedule::command('opsevidence:reconcile-incidents')
    ->hourly()
    ->withoutOverlapping(10);

// El día 1 de cada mes queda el informe del mes anterior generado, listo para
// revisar y enviar. No se envía solo: enviar al cliente es una decisión humana.
Schedule::command('opsevidence:generate-monthly-reports')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping(120);

Schedule::command('opsevidence:prune-evidence')
    ->dailyAt('03:30')
    ->withoutOverlapping(60);
