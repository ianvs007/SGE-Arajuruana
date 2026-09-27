<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioCurso extends Model
{
    protected $table = 'horarios_curso';

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

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'activo' => 'boolean',
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
        ];
    }

    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public const TURNOS = [
        'manana' => 'Mañana',
        'tarde' => 'Tarde',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function nombreDia(): string
    {
        return self::DIAS[$this->dia_semana] ?? (string) $this->dia_semana;
    }

    public function nombreTurno(): string
    {
        return self::TURNOS[$this->turno] ?? $this->turno;
    }
}
