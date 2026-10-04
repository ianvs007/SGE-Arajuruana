<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Curso (tabla `cursos`).
 *
 * Representa un curso o paralelo de la unidad educativa dentro de una gestión
 * (por ejemplo, "3ro de Secundaria A, turno mañana"). Es el punto de unión
 * entre la parte académica y la administrativa: a un curso se inscriben los
 * estudiantes, se le asignan docentes, tiene horarios y puede recibir avisos.
 *
 * Se relaciona con Gestion, Inscripcion, Estudiante, User (docentes, mediante
 * la tabla pivote `docente_curso`), Aviso y HorarioCurso.
 */
class Curso extends Model
{
    /**
     * Campos asignables de forma masiva:
     * - nombre: nombre visible del curso.
     * - nivel: nivel educativo (por ejemplo, primaria o secundaria).
     * - grado: grado dentro del nivel (por ejemplo, "1ro" o "3ro").
     * - paralelo: letra del paralelo (A, B, ...).
     * - turno: turno en el que funciona, ver la constante TURNOS.
     * - orden: número usado para listar los cursos en un orden lógico.
     * - gestion_id: gestión escolar a la que pertenece el curso.
     * - anio_lectivo: campo antiguo que conservamos mientras se completa la
     *   transición hacia la relación con la tabla de gestiones.
     * - activo: permite desactivar el curso sin borrarlo.
     */
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

    /** Conversión de tipos para trabajar con booleanos y enteros reales. */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'anio_lectivo' => 'integer',
            'orden' => 'integer',
        ];
    }

    /**
     * Turnos posibles de un curso. A diferencia del horario, un curso puede ser
     * "mixto" cuando pasa clases tanto en la mañana como en la tarde.
     */
    public const TURNOS = [
        'manana' => 'Mañana',
        'tarde' => 'Tarde',
        'mixto' => 'Mixto (mañana y tarde)',
    ];

    /**
     * Relación "pertenece a" con la gestión escolar del curso.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación "uno a muchos" con las inscripciones.
     * Es la forma correcta de saber qué estudiantes cursan aquí en una gestión.
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * Relación "uno a muchos" con los estudiantes a través del campo antiguo
     * `curso_id` de la tabla de estudiantes. La mantenemos por compatibilidad
     * mientras se termina de migrar todo hacia las inscripciones.
     */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class);
    }

    /**
     * Relación "muchos a muchos" con los docentes (usuarios) asignados al curso.
     * En la tabla pivote guardamos además la gestión y el rol del docente
     * (por ejemplo, docente de aula), porque un docente puede tener distintos
     * cursos en distintas gestiones.
     */
    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'docente_curso')
            ->withPivot(['gestion_id', 'rol_docente'])
            ->withTimestamps();
    }

    /**
     * Relación "uno a muchos" con los avisos dirigidos específicamente a este curso.
     */
    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class);
    }

    /**
     * Relación "uno a muchos" con los horarios del curso.
     * Solo devolvemos los horarios activos y ya ordenados por día y hora de
     * inicio, que es como se necesitan para mostrar el horario semanal.
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioCurso::class)
            ->where('activo', true)
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio');
    }

    /**
     * Devuelve el nombre legible del turno.
     * Si el curso no tiene turno definido, asumimos "Mañana", que es el turno
     * por defecto de la unidad educativa.
     */
    public function nombreTurno(): string
    {
        return self::TURNOS[$this->turno] ?? 'Mañana';
    }

    /**
     * Arma una etiqueta descriptiva del curso, por ejemplo "3ro Secundaria (A) Gestión 2026".
     * Se usa en listas desplegables y reportes para identificar el curso sin ambigüedad.
     */
    public function etiqueta(): string
    {
        // Usamos el nombre de la gestión y, si aún no existe, el año lectivo antiguo.
        $gestion = $this->gestion?->nombre ?? (string) $this->anio_lectivo;

        // Juntamos solo las partes que tienen valor, para no dejar espacios o paréntesis vacíos.
        $parts = array_filter([
            $this->nombre,
            $this->paralelo ? "({$this->paralelo})" : null,
            $gestion,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Consulta de los estudiantes inscritos actualmente en este curso.
     * Filtramos por inscripciones en estado "activa", de modo que no aparecen
     * los alumnos retirados, trasladados o con inscripción cancelada.
     */
    public function estudiantesInscritos()
    {
        return Estudiante::whereHas('inscripciones', fn ($q) => $q->where('curso_id', $this->id)->where('estado', 'activa'));
    }
}
