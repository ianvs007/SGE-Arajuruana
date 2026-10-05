<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos institucionales (punto 2: contexto exclusivamente boliviano)
    |--------------------------------------------------------------------------
    |
    | Identidad de la unidad educativa usada en correos, comprobantes internos,
    | reportes y textos de WhatsApp. Se puede ajustar por `.env` SIN tocar el
    | código (punto 13: sin secretos ni datos en duro dispersos).
    |
    */

    'nombre' => env('INSTITUCION_NOMBRE', 'Unidad Educativa Arajuruana Fe y Alegría'),
    'sigla' => env('INSTITUCION_SIGLA', 'UE Arajuruana'),
    'distrito' => env('INSTITUCION_DISTRITO', 'San Ignacio de Moxos, Beni — Bolivia'),
    'telefono' => env('INSTITUCION_TELEFONO', ''),
    'direccion' => env('INSTITUCION_DIRECCION', ''),

];
