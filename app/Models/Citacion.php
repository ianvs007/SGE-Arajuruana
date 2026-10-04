<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Citacion (tabla `citaciones`).
 *
 * Representa una citación que el colegio dirige al responsable familiar de un
 * estudiante para tratar algún tema (rendimiento, conducta, etc.). Puede
 * emitirla un Docente (solo para alumnos de sus cursos), la Dirección o
 * Administración.
 *
 * En la citación registramos el destinatario, el alumno, el motivo, quién la
 * emitió y la fecha y hora asignadas; el responsable familiar se ajusta a esa
 * convocatoria, ya que no manejamos un sistema de reserva de citas. También se
 * guardan los acuerdos alcanzados, el responsable del seguimiento y una fecha
 * de revisión. Si la citación proviene de una incidencia confidencial, el texto
 * que ve la familia no reproduce el detalle de esa incidencia.
 *
 * Se relaciona con Estudiante, User (padre citado, responsable del seguimiento
 * y emisor) e Incidencia.
 */
class Citacion extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'citaciones';

    /**
     * Campos asignables de forma masiva:
     * - estudiante_id: alumno por el que se cita.
     * - padre_id: responsable familiar citado.
     * - fecha / hora: momento asignado para la reunión.
     * - motivo / descripcion: razón de la citación y detalle visible para la familia.
     * - incidencia_id: incidencia relacionada, si la hay.
     * - estado: situación de la citación, ver la constante ESTADOS.
     * - acuerdos: compromisos alcanzados en la reunión.
     * - observaciones_seguimiento: notas sobre el cumplimiento de los acuerdos.
     * - seguimiento_responsable_id: usuario encargado de hacer el seguimiento.
     * - fecha_revision: día en que se deben revisar los acuerdos.
     * - generado_por: usuario que emitió la citación.
     */
    protected $fillable = [
        'estudiante_id',
        'padre_id',
        'fecha',
        'hora',
        'motivo',
        'descripcion',
        'incidencia_id',
        'estado',
        'acuerdos',
        'observaciones_seguimiento',
        'seguimiento_responsable_id',
        'fecha_revision',
        'generado_por',
    ];

    /** Convertimos la fecha de la cita y la de revisión a objetos de fecha. */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_revision' => 'date',
        ];
    }

    /** Estados sencillos para seguir la atención de la citación y sus acuerdos. */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'atendida' => 'Atendida',
        'no_asistio' => 'No asistió',
        'en_seguimiento' => 'En seguimiento',
        'cerrada' => 'Cerrada',
        'cancelada' => 'Cancelada',
    ];

    /**
     * Relación "pertenece a" con el estudiante por el que se emite la citación.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación con el responsable familiar (usuario) que recibe la citación.
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    /**
     * Relación con la incidencia que originó la citación, si existe.
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    /**
     * Relación con el usuario encargado de dar seguimiento a los acuerdos.
     */
    public function seguimientoResponsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seguimiento_responsable_id');
    }

    /**
     * Relación con el usuario que emitió la citación.
     */
    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    /**
     * Devuelve el texto legible del estado de la citación.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Indica si la citación necesita una revisión de sus acuerdos.
     *
     * Para ello debe tener fecha de revisión y estar atendida o en seguimiento.
     * Si se indica la fecha de hoy, además se exige que la revisión ya haya
     * llegado o esté vencida; si no se indica, basta con que tenga revisión pendiente.
     *
     * @param  string|null  $hoy  Fecha de referencia en formato Y-m-d.
     */
    public function requiereRevision(?string $hoy = null): bool
    {
        return $this->fecha_revision !== null
            && in_array($this->estado, ['atendida', 'en_seguimiento'], true)
            && ($hoy === null || $this->fecha_revision->format('Y-m-d') <= $hoy);
    }
}
