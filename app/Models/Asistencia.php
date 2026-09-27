<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
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

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /** Estados confirmados (§9). "Sin registro" NO es un estado: es la ausencia de fila. */
    public const ESTADOS = [
        'presente' => 'Presente',
        'ausente' => 'Ausente',
        'atrasado' => 'Atrasado',
        'justificada' => 'Ausencia justificada',
    ];

    public const TURNOS = [
        'manana' => 'Mañana',
        'tarde' => 'Tarde',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function modificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function nombreTurno(): string
    {
        return self::TURNOS[$this->turno] ?? $this->turno;
    }
}
