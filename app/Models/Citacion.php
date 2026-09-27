<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Citación (§12).
 *
 * - Emitida por Docente (solo alumnos de sus cursos), Dirección o Administración.
 * - Registra destinatario, alumno, motivo, emisor, fecha y hora asignadas.
 *   El responsable familiar se ajusta a la convocatoria (sin reserva de citas).
 * - Incluye acuerdos, responsable de seguimiento y fecha de revisión.
 * - Si hay incidencia asociada confidencial, el texto dirigido al familiar
 *   NO reproduce su detalle (§11/§12).
 */
class Citacion extends Model
{
    protected $table = 'citaciones';

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

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_revision' => 'date',
        ];
    }

    /** Estados simples de atención y seguimiento (§12). */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'atendida' => 'Atendida',
        'no_asistio' => 'No asistió',
        'en_seguimiento' => 'En seguimiento',
        'cerrada' => 'Cerrada',
        'cancelada' => 'Cancelada',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    public function seguimientoResponsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seguimiento_responsable_id');
    }

    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** ¿Tiene seguimiento vigente (acuerdos con fecha de revisión futura o vencida)? */
    public function requiereRevision(?string $hoy = null): bool
    {
        return $this->fecha_revision !== null
            && in_array($this->estado, ['atendida', 'en_seguimiento'], true)
            && ($hoy === null || $this->fecha_revision->format('Y-m-d') <= $hoy);
    }
}
