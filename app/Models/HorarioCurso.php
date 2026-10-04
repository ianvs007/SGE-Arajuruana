<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo HorarioCurso (tabla `horarios_curso`).
 *
 * Representa un bloque de horario de un curso: qué día de la semana, en qué
 * turno y entre qué horas pasa clases. Lo usamos para saber cuándo un curso
 * tiene actividad, por ejemplo al momento de registrar la asistencia por turno.
 *
 * Cada horario pertenece a un único curso (Curso). En la base de datos existe
 * una restricción única por curso, día, turno y hora de inicio, para que no se
 * registren dos bloques idénticos.
 */
class HorarioCurso extends Model
{
    /** Indicamos el nombre de la tabla porque no sigue la convención plural de Laravel. */
    protected $table = 'horarios_curso';

    /**
     * Campos que se pueden asignar de forma masiva:
     * - curso_id: curso al que pertenece el bloque de horario.
     * - dia_semana: número del día (1 = lunes ... 7 = domingo), ver la constante DIAS.
     * - turno: clave del turno ('manana' o 'tarde'), ver la constante TURNOS.
     * - hora_inicio / hora_fin: rango horario del bloque.
     * - activo: permite desactivar un horario sin borrarlo.
     * - vigente_desde / vigente_hasta: periodo en el que el horario es válido,
     *   útil cuando el horario cambia a mitad de gestión y queremos conservar el anterior.
     */
    protected $fillable = [
        'curso_id',
        'dia_semana',
        'turno',
        'hora_inicio',
        'hora_fin',
        'activo',
        'vigente_desde',
        'vigente_hasta',
    ];

    /**
     * Conversión automática de tipos al leer los atributos.
     * El día se maneja como entero, el estado activo como booleano y las fechas
     * de vigencia como objetos de fecha para poder compararlas fácilmente.
     */
    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'activo' => 'boolean',
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
        ];
    }

    /**
     * Nombres de los días de la semana según el número guardado en `dia_semana`.
     * Seguimos la numeración ISO, donde la semana empieza el lunes (1).
     */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * Turnos en los que puede funcionar un horario. Guardamos la clave sin ñ
     * ('manana') en la base de datos y mostramos el texto correcto en pantalla.
     */
    public const TURNOS = [
        'manana' => 'Mañana',
        'tarde' => 'Tarde',
    ];

    /**
     * Relación "pertenece a" con el curso.
     * Nos permite saber a qué curso corresponde este bloque de horario.
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Devuelve el nombre legible del día (por ejemplo, "Lunes").
     * Si el número no está en la lista, mostramos el número tal cual para no romper la vista.
     */
    public function nombreDia(): string
    {
        return self::DIAS[$this->dia_semana] ?? (string) $this->dia_semana;
    }

    /**
     * Devuelve el nombre legible del turno (por ejemplo, "Mañana").
     * Si la clave no es conocida, se devuelve el valor original.
     */
    public function nombreTurno(): string
    {
        return self::TURNOS[$this->turno] ?? $this->turno;
    }
}
