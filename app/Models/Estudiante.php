<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    protected $fillable = [
        'codigo',
        'nombres',
        'apellidos',
        'documento',
        'fecha_nacimiento',
        'sexo',
        'curso_id', // transición: la relación canónica es vía inscripciones
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    public const ESTADOS = [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
        'retirado' => 'Retirado',
        'trasladado' => 'Trasladado',
        'graduado' => 'Graduado',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /** Inscripción de una gestión (o la actual por defecto). */
    public function inscripcionEn(?Gestion $gestion = null): ?Inscripcion
    {
        $gestion ??= Gestion::actual();
        if (! $gestion) {
            return null;
        }

        return $this->inscripciones()
            ->where('gestion_id', $gestion->id)
            ->with('curso')
            ->first();
    }

    /** Curso actual vía inscripción activa; cae a curso_id durante la transición. */
    public function cursoActual(): ?Curso
    {
        return $this->inscripcionEn()?->curso ?? $this->curso;
    }

    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'estudiante_padre', 'estudiante_id', 'padre_id')
            ->withPivot('parentesco', 'es_principal')
            ->withTimestamps();
    }

    /** Alias de compatibilidad con vistas/controladores existentes. */
    public function padres(): BelongsToMany
    {
        return $this->responsables();
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function salidas(): HasMany
    {
        return $this->hasMany(SalidaEstudiante::class);
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }

    public function citaciones(): HasMany
    {
        return $this->hasMany(Citacion::class);
    }

    /** Cuotas de aporte del alumno (§14: la obligación es del alumno). */
    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaAporte::class);
    }

    /** Avisos dirigidos a los responsables de este alumno (§13). */
    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class);
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->apellidos} {$this->nombres}");
    }

    public function esRepresentadoPor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->responsables()->whereKey($user->getKey())->exists();
    }
}
