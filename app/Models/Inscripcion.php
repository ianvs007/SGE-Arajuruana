<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscripcion extends Model
{
    protected $table = 'inscripciones';

    protected $fillable = [
        'estudiante_id',
        'gestion_id',
        'curso_id',
        'estado',
        'fecha_inscripcion',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inscripcion' => 'date',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    /** Cuotas generadas para esta inscripción (§14). */
    public function cuotas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CuotaAporte::class);
    }
}
