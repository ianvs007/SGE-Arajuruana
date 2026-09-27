<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aviso institucional (§13).
 *
 * Alcances (`audiencia`):
 * - `todos`         → toda la comunidad con cuenta activa.
 * - `padres`        → todos los responsables familiares.
 * - `docentes`      → todos los docentes.
 * - `administrativos`→ roles institucionales (Administración/Dirección/Coord.).
 * - `curso`         → responsables de los alumnos del curso + sus docentes.
 * - `familia`       → responsables de UN alumno (aviso dirigido).
 *
 * Al PUBLICAR se materializan los destinatarios en `aviso_destinatarios`, de modo
 * que quede trazable a quién se avisó aunque después cambien inscripciones o
 * responsables.
 *
 * La confirmación de lectura es OPCIONAL y NO BLOQUEANTE (§13): `requiere_confirmacion`
 * solo habilita el registro de quién confirmó; nunca impide usar el sistema ni
 * oculta información al que no confirmó.
 */
class Aviso extends Model
{
    protected $fillable = [
        'titulo',
        'contenido',
        'tipo',
        'audiencia',
        'curso_id',
        'estudiante_id',
        'requiere_confirmacion',
        'confirmar_antes',
        'publicado',
        'publicado_en',
        'enviado_en',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'publicado' => 'boolean',
            'requiere_confirmacion' => 'boolean',
            'confirmar_antes' => 'datetime',
            'publicado_en' => 'datetime',
            'enviado_en' => 'datetime',
        ];
    }

    /** Tipos de aviso (contenido, no alcance). */
    public const TIPOS = [
        'institucional' => 'Institucional',
        'administrativo' => 'Administrativo',
        'seguimiento' => 'Seguimiento',
        'citacion' => 'Citación',
        'academico' => 'Académico',
        'economico' => 'Económico',
    ];

    /** Alcances disponibles (§13). */
    public const AUDIENCIAS = [
        'todos' => 'Toda la comunidad',
        'padres' => 'Responsables familiares',
        'docentes' => 'Docentes',
        'administrativos' => 'Administración y Dirección',
        'curso' => 'Un curso (responsables y docentes)',
        'familia' => 'Responsables de un alumno',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(AvisoDestinatario::class);
    }

    public function nombreTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function nombreAudiencia(): string
    {
        return self::AUDIENCIAS[$this->audiencia] ?? ucfirst($this->audiencia);
    }

    /** Descripción legible del alcance (curso o alumno concreto). */
    public function descripcionAlcance(): string
    {
        if ($this->audiencia === 'curso' && $this->curso) {
            return $this->curso->nombre.($this->curso->turno ? ' ('.$this->curso->turno.')' : '');
        }

        if ($this->audiencia === 'familia' && $this->estudiante) {
            return $this->estudiante->nombreCompleto();
        }

        return $this->nombreAudiencia();
    }

    /**
     * Avisos VISIBLES para el usuario dado (§6, §13).
     *
     * Regla: solo publicados; y el usuario debe ser destinatario materializado.
     * Los borradores solo los ven quienes pueden gestionarlos.
     */
    public static function scopeParaUsuario(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('publicado', true)
                ->whereHas('destinatarios', fn (Builder $d) => $d->where('user_id', $user->id));

            if ($user->can('avisos.gestionar')) {
                $q->orWhere('creado_por', $user->id);
            }
        });
    }

    /** ¿Este usuario es destinatario del aviso? */
    public function esDestinatario(User $user): bool
    {
        return $this->destinatarios()->where('user_id', $user->id)->exists();
    }

    public function destinatarioDe(User $user): ?AvisoDestinatario
    {
        return $this->destinatarios()->where('user_id', $user->id)->first();
    }

    /** Progreso de confirmación (para el emisor; §13 no bloqueante). */
    public function progresoConfirmacion(): array
    {
        $total = $this->destinatarios()->count();
        $leidos = $this->destinatarios()->whereNotNull('leido_en')->count();
        $confirmados = $this->destinatarios()->whereNotNull('confirmado_en')->count();

        return [
            'total' => $total,
            'leidos' => $leidos,
            'confirmados' => $confirmados,
            'porcentaje_leidos' => $total > 0 ? (int) round(($leidos / $total) * 100) : 0,
        ];
    }

    /** @return Collection<int, AvisoDestinatario> */
    public function pendientesDeConfirmacion(): Collection
    {
        return $this->destinatarios()->whereNull('confirmado_en')->with('usuario')->get();
    }
}
