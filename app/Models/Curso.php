<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    protected $fillable = [
        'nombre',
        'nivel',
        'grado',
        'paralelo',
        'turno',
        'orden',
        'gestion_id',
        'anio_lectivo', // transición: se conserva mientras migra a gestión
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'anio_lectivo' => 'integer',
            'orden' => 'integer',
        ];
    }

    public const TURNOS = [
        'manana' => 'Mañana',
        'tarde' => 'Tarde',
        'mixto' => 'Mixto (mañana y tarde)',
    ];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class);
    }

    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'docente_curso')
            ->withPivot(['gestion_id', 'rol_docente'])
            ->withTimestamps();
    }

    /** Avisos dirigidos a este curso (§13). */
    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioCurso::class)
            ->where('activo', true)
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio');
    }

    public function nombreTurno(): string
    {
        return self::TURNOS[$this->turno] ?? 'Mañana';
    }

    public function etiqueta(): string
    {
        $gestion = $this->gestion?->nombre ?? (string) $this->anio_lectivo;
        $parts = array_filter([
            $this->nombre,
            $this->paralelo ? "({$this->paralelo})" : null,
            $gestion,
        ]);

        return implode(' ', $parts);
    }

    /** Estudiantes inscritos en este curso en la gestión del curso. */
    public function estudiantesInscritos()
    {
        return Estudiante::whereHas('inscripciones', fn ($q) => $q->where('curso_id', $this->id)->where('estado', 'activa'));
    }
}
