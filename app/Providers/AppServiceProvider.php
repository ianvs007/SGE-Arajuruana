<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Proveedor de servicios principal de la aplicación.
 *
 * Laravel ejecuta esta clase al arrancar el sistema. Es el lugar indicado para
 * registrar servicios propios o configurar comportamientos globales. Por ahora
 * no necesitamos ninguna configuración adicional, por lo que sus métodos están
 * vacíos, pero la mantenemos porque Laravel la carga por defecto.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios en el contenedor de Laravel.
     * Aquí se enlazarían clases o interfaces propias antes de que la aplicación arranque.
     */
    public function register(): void
    {
        //
    }

    /**
     * Se ejecuta cuando todos los servicios ya fueron registrados.
     * Aquí se colocaría la configuración global (por ejemplo, reglas de validación o
     * ajustes de las vistas) que deba aplicarse en toda la aplicación.
     */
    public function boot(): void
    {
        //
    }
}
