<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de respaldo manual (§17).
 *
 * El archivo físico vive FUERA de `public/` (disco `privado`, en
 * `storage/app/privado/respaldos`), de modo que nunca es accesible por URL
 * directa. La descarga se sirve desde `RespaldoController` con el permiso
 * `respaldos.gestionar`. Se guarda checksum SHA-256 para poder verificar
 * integridad antes de restaurar.
 */
class Respaldo extends Model
{
    protected $table = 'respaldos';

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

    protected function casts(): array
    {
        return [
            'incluye_env' => 'boolean',
            'tamano_bytes' => 'integer',
            'tablas' => 'integer',
        ];
    }

    public const ESTADOS = [
        'ok' => 'Correcto',
        'error' => 'Fallido',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Nombre de archivo legible para descarga (sin exponer la ruta física). */
    public function nombreDescarga(): string
    {
        return basename($this->archivo);
    }

    /** Ruta absoluta dentro del disco privado. */
    public function rutaPrivada(): string
    {
        return $this->archivo;
    }

    public function tamanoLegible(): string
    {
        $bytes = (int) $this->tamano_bytes;
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $tam = (float) $bytes;
        while ($tam >= 1024 && $i < count($unidades) - 1) {
            $tam /= 1024;
            $i++;
        }

        return number_format($tam, $i === 0 ? 0 : 2, ',', '.').' '.$unidades[$i];
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function creadoEl(): Carbon
    {
        return $this->created_at;
    }
}
