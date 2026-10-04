<?php

/*
|--------------------------------------------------------------------------
| Comandos de consola
|--------------------------------------------------------------------------
|
| En este archivo se pueden registrar comandos Artisan sencillos definidos con
| funciones anónimas. Por ahora solo dejamos el comando de ejemplo que trae
| Laravel; las tareas del sistema se ejecutan desde la interfaz web.
|
*/

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Comando de ejemplo "php artisan inspire": muestra una frase inspiradora en
// la consola. Sirve para comprobar rápidamente que Artisan funciona.
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
