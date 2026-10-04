<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Inscripcion (tabla `inscripciones`).
 *
 * Representa la inscripción de un estudiante en un curso durante una gestión.
 * Es la relación oficial entre alumno y curso: como un estudiante cambia de
 * curso cada año, guardar la inscripción por gestión nos permite conservar el
 * historial completo de dónde estudió en cada año.
 *
 * Se relaciona con Estudiante, Gestion, Curso, Asistencia y CuotaAporte.
 */
class Inscripcion extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'inscripciones';

    /**
     * Campos asignables de forma masiva:
     * - estudiante_id: alumno inscrito.
     * - gestion_id: gestión escolar de la inscripción.
     * - curso_id: curso en el que queda inscrito.
     * - estado: situación de la inscripción (activa, retirada, trasladada o cancelada).
     * - fecha_inscripcion: día en que se realizó la inscripción.
     * - observaciones: notas adicionales de la administración.
     */
    protected $fillable = [
        'estudiante_id',
        'gestion_id',
        'curso_id',
        'estado',
        'fecha_inscripcion',
        'observaciones',
    ];

    /** Convertimos la fecha de inscripción a objeto de fecha. */
    protected function casts(): array
    {
        return [
            'fecha_inscripcion' => 'date',
        ];
    }

    /**
     * Relación "pertenece a" con el estudiante inscrito.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación "pertenece a" con la gestión de la inscripción.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación "pertenece a" con el curso en el que se inscribió el estudiante.
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación "uno a muchos" con las asistencias registradas bajo esta inscripción.
     * Así la asistencia queda ligada al año y curso concretos del alumno.
     */
    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Relación "uno a muchos" con las cuotas de aporte generadas para esta inscripción.
     * Las cuotas se emiten por alumno inscrito, mes a mes, dentro de la gestión.
     */
    public function cuotas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CuotaAporte::class);
    }
}
