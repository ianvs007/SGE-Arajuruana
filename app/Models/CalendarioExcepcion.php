<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo CalendarioExcepcion (tabla `calendario_excepciones`).
 *
 * Registra los días en los que no hay clases normales dentro de una gestión:
 * feriados, jornadas sin clases o actividades internas del colegio. Gracias a
 * esta tabla el sistema puede distinguir un día sin asistencia registrada de
 * un día en el que simplemente no correspondía pasar clases.
 *
 * Se relaciona con la gestión (Gestion) y, de forma opcional, con un curso
 * (Curso). Si `curso_id` queda vacío, la excepción aplica a todo el colegio;
 * si tiene un curso, solo afecta a ese curso.
 */
class CalendarioExcepcion extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'calendario_excepciones';

    /**
     * Campos asignables de forma masiva:
     * - gestion_id: gestión escolar a la que pertenece la excepción.
     * - curso_id: curso afectado (nulo cuando aplica a toda la unidad educativa).
     * - fecha: día de la excepción.
     * - tipo: clase de excepción, ver las constantes TIPO_*.
     * - motivo: explicación breve (por ejemplo, "Día del Maestro").
     */
    protected $fillable = [
        'gestion_id',
        'curso_id',
        'fecha',
        'tipo',
        'motivo',
    ];

    /** Convertimos la fecha a objeto de fecha para poder compararla y formatearla. */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /** Jornada sin clases (por ejemplo, por clima o disposición de la Dirección Distrital). */
    public const TIPO_SIN_CLASES = 'sin_clases';

    /** Feriado nacional o departamental. */
    public const TIPO_FERIADO = 'feriado';

    /** Actividad interna del colegio que reemplaza las clases (aniversario, horas cívicas, etc.). */
    public const TIPO_ACTIVIDAD_INTERNA = 'actividad_interna';

    /** Lista de tipos con su texto legible, para formularios y listados. */
    public const TIPOS = [
        self::TIPO_SIN_CLASES => 'Jornada sin clases',
        self::TIPO_FERIADO => 'Feriado',
        self::TIPO_ACTIVIDAD_INTERNA => 'Actividad interna',
    ];

    /**
     * Relación "pertenece a" con la gestión.
     * Cada excepción se registra dentro de una gestión escolar concreta.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación "pertenece a" con el curso.
     * Puede ser nula: en ese caso la excepción es general para todo el colegio.
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Devuelve el nombre legible del tipo de excepción.
     * Si el tipo no está registrado en la lista, mostramos el valor original.
     */
    public function nombreTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
