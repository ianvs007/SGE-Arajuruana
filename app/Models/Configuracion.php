<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Modelo Configuracion (tabla `configuraciones`).
 *
 * Funciona como un almacén sencillo de pares clave/valor para guardar ajustes
 * generales del sistema que la administración puede cambiar sin tocar el código
 * (por ejemplo, datos institucionales o parámetros de pago). No tiene relaciones
 * con otras tablas: cada fila es un ajuste independiente.
 */
class Configuracion extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'configuraciones';

    /**
     * Campos asignables:
     * - clave: identificador único del ajuste.
     * - valor: contenido del ajuste, guardado como texto.
     */
    protected $fillable = [
        'clave',
        'valor',
    ];

    /**
     * Lee el valor de una configuración a partir de su clave.
     *
     * Guardamos el resultado en caché durante 60 segundos para no consultar la
     * base de datos cada vez que una vista necesita el mismo ajuste. Si la clave
     * no existe, se devuelve el valor por defecto indicado.
     *
     * @param  string  $clave  Clave del ajuste que se quiere leer.
     * @param  string|null  $default  Valor a devolver si la clave no está registrada.
     */
    public static function getValor(string $clave, ?string $default = null): ?string
    {
        return Cache::remember("config.{$clave}", 60, function () use ($clave, $default) {
            $item = self::query()->where('clave', $clave)->first();

            return $item?->valor ?? $default;
        });
    }

    /**
     * Guarda (crea o actualiza) el valor de una configuración.
     *
     * Después de guardar borramos la entrada de la caché; de lo contrario, el
     * sistema seguiría mostrando el valor antiguo hasta que expire la caché.
     */
    public static function setValor(string $clave, ?string $valor): void
    {
        // Si la clave ya existe se actualiza; si no, se crea un registro nuevo.
        self::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget("config.{$clave}");
    }
}
