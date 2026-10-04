<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modelo Respaldo (tabla `respaldos`).
 *
 * Guarda el historial de los respaldos (copias de seguridad) de la base de datos
 * que se generan manualmente desde el sistema. El archivo físico se almacena
 * fuera de la carpeta `public/`, en el disco privado (`storage/app/privado/respaldos`),
 * para que nunca se pueda descargar escribiendo su dirección en el navegador.
 * La descarga solo se permite desde `RespaldoController` y a usuarios con el
 * permiso `respaldos.gestionar`. Además guardamos una suma de verificación
 * SHA-256 para comprobar que el archivo no se dañó antes de restaurarlo.
 *
 * Se relaciona con User (quién generó el respaldo).
 */
class Respaldo extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'respaldos';

    /**
     * Campos asignables de forma masiva:
     * - archivo: ruta del archivo dentro del disco privado.
     * - tamano_bytes: tamaño del archivo en bytes.
     * - checksum: suma de verificación SHA-256 para comprobar la integridad.
     * - motor / base_datos: motor de base de datos (por ejemplo, mysql) y nombre de la base respaldada.
     * - tablas: cantidad de tablas incluidas en el respaldo.
     * - incluye_env: indica si se incluyó el archivo de configuración `.env`.
     * - estado / error: si el respaldo salió bien o falló, y el mensaje de error.
     * - notas: comentario libre de quien lo generó.
     * - creado_por: usuario que generó el respaldo.
     */
    protected $fillable = [
        'archivo',
        'tamano_bytes',
        'checksum',
        'motor',
        'base_datos',
        'tablas',
        'incluye_env',
        'estado',
        'error',
        'notas',
        'creado_por',
    ];

    /** Conversión de tipos para trabajar con enteros y booleanos reales. */
    protected function casts(): array
    {
        return [
            'incluye_env' => 'boolean',
            'tamano_bytes' => 'integer',
            'tablas' => 'integer',
        ];
    }

    /** Resultado del proceso de respaldo. */
    public const ESTADOS = [
        'ok' => 'Correcto',
        'error' => 'Fallido',
    ];

    /**
     * Relación con el usuario que generó el respaldo.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Devuelve solo el nombre del archivo, que es lo que ve el usuario al
     * descargarlo, sin mostrar la ruta interna donde está guardado.
     */
    public function nombreDescarga(): string
    {
        return basename($this->archivo);
    }

    /**
     * Devuelve la ruta del archivo dentro del disco privado, que es la que se
     * usa internamente para leerlo o descargarlo.
     */
    public function rutaPrivada(): string
    {
        return $this->archivo;
    }

    /**
     * Convierte el tamaño en bytes a un texto fácil de leer, por ejemplo "2,35 MB".
     */
    public function tamanoLegible(): string
    {
        $bytes = (int) $this->tamano_bytes;
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $tam = (float) $bytes;

        // Dividimos entre 1024 hasta que el número sea menor a 1024 o lleguemos a la unidad más grande.
        while ($tam >= 1024 && $i < count($unidades) - 1) {
            $tam /= 1024;
            $i++;
        }

        // Los bytes se muestran sin decimales; las demás unidades con dos, usando coma decimal.
        return number_format($tam, $i === 0 ? 0 : 2, ',', '.').' '.$unidades[$i];
    }

    /**
     * Devuelve el texto legible del estado del respaldo.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Devuelve la fecha y hora en que se generó el respaldo.
     */
    public function creadoEl(): Carbon
    {
        return $this->created_at;
    }
}
