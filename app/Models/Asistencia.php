<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Asistencia (tabla `asistencias`).
 *
 * Guarda el registro de asistencia de un estudiante en una fecha y turno
 * determinados. Solo se crea una fila cuando la administración registra la
 * asistencia; por eso, si no existe registro, el sistema lo interpreta como
 * "sin registro" y no como una falta.
 *
 * En la base de datos existe una restricción única por estudiante, fecha y
 * turno para evitar asistencias duplicadas. Se relaciona con Estudiante,
 * Inscripcion, Curso y User (quién registró y quién modificó).
 */
class Asistencia extends Model
{
    /**
     * Campos asignables de forma masiva:
     * - estudiante_id: alumno al que corresponde la asistencia.
     * - inscripcion_id: inscripción vigente del alumno al momento del registro.
     * - curso_id: curso en el que se tomó la asistencia.
     * - fecha / turno: día y turno del registro.
     * - estado: presente, ausente, atrasado o ausencia justificada.
     * - observacion: comentario opcional (por ejemplo, el motivo de una justificación).
     * - registrado_por / modificado_por: usuarios que crearon y modificaron el
     *   registro, para mantener la trazabilidad.
     */
    protected $fillable = [
        'estudiante_id',
        'inscripcion_id',
        'curso_id',
        'fecha',
        'turno',
        'estado',
        'observacion',
        'registrado_por',
        'modificado_por',
    ];

    /** Convertimos la fecha a objeto de fecha. */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * Estados de asistencia acordados con la institución.
     * "Sin registro" no aparece aquí a propósito: no es un estado, sino la
     * ausencia de una fila para ese alumno, fecha y turno.
     */
    public const ESTADOS = [
        'presente' => 'Presente',
        'ausente' => 'Ausente',
        'atrasado' => 'Atrasado',
        'justificada' => 'Ausencia justificada',
    ];

    /** Turnos en los que se puede registrar la asistencia. */
    public const TURNOS = [
        'manana' => 'Mañana',
        'tarde' => 'Tarde',
    ];

    /**
     * Relación "pertenece a" con el estudiante.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación "pertenece a" con la inscripción del estudiante en la gestión.
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    /**
     * Relación "pertenece a" con el curso donde se registró la asistencia.
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación con el usuario que registró la asistencia por primera vez.
     */
    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Relación con el último usuario que modificó la asistencia.
     * Nos sirve para saber quién hizo una corrección.
     */
    public function modificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }

    /**
     * Devuelve el nombre legible del estado de asistencia.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Devuelve el nombre legible del turno.
     */
    public function nombreTurno(): string
    {
        return self::TURNOS[$this->turno] ?? $this->turno;
    }
}
