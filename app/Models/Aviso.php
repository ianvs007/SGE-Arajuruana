<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Aviso (tabla `avisos`).
 *
 * Representa un aviso o comunicado institucional que el colegio publica dentro
 * del sistema. Según su alcance (campo `audiencia`) puede llegar a:
 * - todos: toda la comunidad que tiene una cuenta activa.
 * - padres: todos los responsables familiares.
 * - docentes: todos los docentes.
 * - administrativos: los roles institucionales (Administración, Dirección y Coordinación).
 * - curso: los responsables de los alumnos de un curso y sus docentes.
 * - familia: los responsables de un solo alumno (aviso dirigido).
 *
 * Cuando el aviso se publica, guardamos la lista concreta de destinatarios en
 * la tabla `aviso_destinatarios`. Así queda constancia de a quién se avisó,
 * aunque después cambien las inscripciones o los responsables de un alumno.
 *
 * La confirmación de lectura es opcional y no bloquea nada: el campo
 * `requiere_confirmacion` solo habilita el registro de quién confirmó, pero
 * nunca impide usar el sistema ni oculta información a quien no confirmó.
 *
 * Se relaciona con User (creador), Curso, Estudiante y AvisoDestinatario.
 */
class Aviso extends Model
{
    /**
     * Campos asignables de forma masiva:
     * - titulo / contenido: texto del aviso.
     * - tipo: naturaleza del contenido, ver la constante TIPOS.
     * - audiencia: alcance del aviso, ver la constante AUDIENCIAS.
     * - curso_id: curso destinatario cuando el alcance es "curso".
     * - estudiante_id: alumno cuyos responsables reciben el aviso cuando el alcance es "familia".
     * - requiere_confirmacion / confirmar_antes: si se pide confirmar la lectura y hasta qué fecha.
     * - publicado / publicado_en: si el aviso ya es visible y desde cuándo.
     * - enviado_en: momento en que se enviaron las notificaciones.
     * - creado_por: usuario que redactó el aviso.
     */
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

    /** Convertimos las banderas a booleanos y las marcas de tiempo a fecha y hora. */
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

    /** Tipos de aviso según su contenido (no según a quién va dirigido). */
    public const TIPOS = [
        'institucional' => 'Institucional',
        'administrativo' => 'Administrativo',
        'seguimiento' => 'Seguimiento',
        'citacion' => 'Citación',
        'academico' => 'Académico',
        'economico' => 'Económico',
    ];

    /** Alcances disponibles, es decir, a qué grupo de usuarios llega el aviso. */
    public const AUDIENCIAS = [
        'todos' => 'Toda la comunidad',
        'padres' => 'Responsables familiares',
        'docentes' => 'Docentes',
        'administrativos' => 'Administración y Dirección',
        'curso' => 'Un curso (responsables y docentes)',
        'familia' => 'Responsables de un alumno',
    ];

    /**
     * Relación con el usuario que creó el aviso.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Relación con el curso destinatario (solo cuando el alcance es "curso").
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación con el alumno cuyos responsables reciben el aviso (alcance "familia").
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación "uno a muchos" con los destinatarios registrados al publicar el aviso.
     */
    public function destinatarios(): HasMany
    {
        return $this->hasMany(AvisoDestinatario::class);
    }

    /**
     * Devuelve el nombre legible del tipo de aviso.
     * Si el tipo no está en la lista, mostramos el valor con la primera letra en mayúscula.
     */
    public function nombreTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    /**
     * Devuelve el nombre legible del alcance del aviso.
     */
    public function nombreAudiencia(): string
    {
        return self::AUDIENCIAS[$this->audiencia] ?? ucfirst($this->audiencia);
    }

    /**
     * Devuelve una descripción concreta del alcance: el nombre del curso o del
     * alumno cuando el aviso es dirigido; en los demás casos, el nombre del alcance.
     */
    public function descripcionAlcance(): string
    {
        // Para avisos de curso mostramos el nombre del curso y, si lo tiene, su turno.
        if ($this->audiencia === 'curso' && $this->curso) {
            return $this->curso->nombre.($this->curso->turno ? ' ('.$this->curso->turno.')' : '');
        }

        // Para avisos a una familia mostramos el nombre del alumno.
        if ($this->audiencia === 'familia' && $this->estudiante) {
            return $this->estudiante->nombreCompleto();
        }

        return $this->nombreAudiencia();
    }

    /**
     * Scope que filtra los avisos que puede ver un usuario.
     *
     * Un usuario ve los avisos publicados en los que figura como destinatario.
     * Además, quien tiene permiso para gestionar avisos ve también los que él
     * mismo creó, incluidos sus borradores, para poder editarlos antes de publicarlos.
     * Se usa así: Aviso::paraUsuario($user)->get().
     */
    public static function scopeParaUsuario(Builder $query, User $user): Builder
    {
        // Agrupamos las condiciones dentro de un where para que el "o" no se mezcle con otros filtros de la consulta.
        return $query->where(function (Builder $q) use ($user) {
            $q->where('publicado', true)
                ->whereHas('destinatarios', fn (Builder $d) => $d->where('user_id', $user->id));

            if ($user->can('avisos.gestionar')) {
                $q->orWhere('creado_por', $user->id);
            }
        });
    }

    /**
     * Indica si el usuario dado figura entre los destinatarios del aviso.
     */
    public function esDestinatario(User $user): bool
    {
        return $this->destinatarios()->where('user_id', $user->id)->exists();
    }

    /**
     * Obtiene el registro de destinatario del usuario dado (con sus datos de
     * lectura y confirmación), o null si no es destinatario.
     */
    public function destinatarioDe(User $user): ?AvisoDestinatario
    {
        return $this->destinatarios()->where('user_id', $user->id)->first();
    }

    /**
     * Calcula el avance de lectura y confirmación del aviso, para que quien lo
     * emitió pueda ver cuántas personas lo leyeron. Es solo informativo: no
     * bloquea a nadie.
     *
     * @return array{total: int, leidos: int, confirmados: int, porcentaje_leidos: int}
     */
    public function progresoConfirmacion(): array
    {
        // Contamos el total de destinatarios, cuántos leyeron y cuántos confirmaron.
        $total = $this->destinatarios()->count();
        $leidos = $this->destinatarios()->whereNotNull('leido_en')->count();
        $confirmados = $this->destinatarios()->whereNotNull('confirmado_en')->count();

        // El porcentaje se redondea a entero y evitamos dividir entre cero si no hay destinatarios.
        return [
            'total' => $total,
            'leidos' => $leidos,
            'confirmados' => $confirmados,
            'porcentaje_leidos' => $total > 0 ? (int) round(($leidos / $total) * 100) : 0,
        ];
    }

    /**
     * Devuelve los destinatarios que todavía no confirmaron la lectura, junto con
     * los datos de su usuario, para mostrarlos en un listado.
     *
     * @return Collection<int, AvisoDestinatario>
     */
    public function pendientesDeConfirmacion(): Collection
    {
        return $this->destinatarios()->whereNull('confirmado_en')->with('usuario')->get();
    }
}
