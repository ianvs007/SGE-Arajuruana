<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Incidencia (§11).
 *
 * - Gestionada solo por Administración (decisión confirmada).
 * - `confidencial = true`: visible ÚNICAMENTE para Administración; nunca se
 *   filtra a reportes, paneles, búsquedas, notificaciones o historial de otros
 *   roles (§11, §20.8).
 * - El texto de una citación asociada no reproduce el detalle confidencial.
 */
class Incidencia extends Model
{
    protected $fillable = [
        'estudiante_id',
        'fecha',
        'tipo',
        'categoria_id',
        'confidencial',
        'descripcion',
        'medida_accion',
        'observaciones',
        'estado_seguimiento',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'confidencial' => 'boolean',
        ];
    }

    public const ESTADOS = [
        'abierta' => 'Abierta',
        'en_seguimiento' => 'En seguimiento',
        'cerrada' => 'Cerrada',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(IncidenciaCategoria::class, 'categoria_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function citaciones(): HasMany
    {
        return $this->hasMany(Citacion::class);
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado_seguimiento] ?? $this->estado_seguimiento;
    }

    /** Etiqueta segura para pantallas de otros roles: nunca revela el detalle. */
    public function etiquetaPublica(): string
    {
        return $this->categoria?->nombre ?? $this->tipo;
    }
}
