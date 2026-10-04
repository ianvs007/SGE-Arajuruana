<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Incidencia (tabla `incidencias`).
 *
 * Registra los hechos de convivencia o disciplina relacionados con un
 * estudiante, junto con la medida tomada y su seguimiento. Por decisión de la
 * institución, las incidencias las gestiona únicamente Administración.
 *
 * Cuando una incidencia se marca como confidencial, solo Administración puede
 * verla: no debe aparecer en reportes, paneles, búsquedas, notificaciones ni en
 * el historial que consultan otros roles. Además, si se emite una citación a
 * partir de ella, el texto de la citación no repite el detalle confidencial.
 *
 * Se relaciona con Estudiante, IncidenciaCategoria, User (quién la registró) y Citacion.
 */
class Incidencia extends Model
{
    /**
     * Campos asignables de forma masiva:
     * - estudiante_id / fecha: alumno involucrado y día del hecho.
     * - tipo: descripción corta del tipo de incidencia (texto libre).
     * - categoria_id: categoría configurable a la que pertenece.
     * - confidencial: si es verdadero, solo Administración puede ver la incidencia.
     * - descripcion: relato de lo ocurrido.
     * - medida_accion: medida o acción tomada por el colegio.
     * - observaciones: notas adicionales.
     * - estado_seguimiento: abierta, en seguimiento o cerrada.
     * - registrado_por: usuario que registró la incidencia.
     */
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

    /** Convertimos la fecha a objeto de fecha y la marca de confidencialidad a booleano. */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'confidencial' => 'boolean',
        ];
    }

    /** Estados del seguimiento de una incidencia. */
    public const ESTADOS = [
        'abierta' => 'Abierta',
        'en_seguimiento' => 'En seguimiento',
        'cerrada' => 'Cerrada',
    ];

    /**
     * Relación "pertenece a" con el estudiante involucrado.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación "pertenece a" con la categoría de la incidencia.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(IncidenciaCategoria::class, 'categoria_id');
    }

    /**
     * Relación con el usuario que registró la incidencia.
     */
    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Relación "uno a muchos" con las citaciones que se emitieron a raíz de esta incidencia.
     */
    public function citaciones(): HasMany
    {
        return $this->hasMany(Citacion::class);
    }

    /**
     * Devuelve el texto legible del estado de seguimiento.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado_seguimiento] ?? $this->estado_seguimiento;
    }

    /**
     * Devuelve una etiqueta segura para mostrar en pantallas de otros roles.
     * Solo mostramos el nombre de la categoría (o el tipo), nunca la descripción,
     * para no revelar detalles sensibles del caso.
     */
    public function etiquetaPublica(): string
    {
        return $this->categoria?->nombre ?? $this->tipo;
    }
}
