<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Estudiante (tabla `estudiantes`).
 *
 * Representa a cada alumno de la unidad educativa. Es la entidad central del
 * sistema: en torno a ella se registran inscripciones, asistencias, salidas,
 * incidencias, citaciones, cuotas de aporte y avisos dirigidos a su familia.
 *
 * Se relaciona con Curso, Inscripcion, User (sus responsables familiares, a
 * través de la tabla pivote `estudiante_padre`), Asistencia, SalidaEstudiante,
 * Incidencia, Citacion, CuotaAporte y Aviso.
 */
class Estudiante extends Model
{
    /**
     * Campos asignables de forma masiva:
     * - codigo: código de identificación del estudiante dentro del colegio.
     * - nombres / apellidos: datos personales del alumno.
     * - documento: número de carnet de identidad.
     * - fecha_nacimiento y sexo: datos personales complementarios.
     * - curso_id: campo antiguo que conservamos durante la transición; el curso
     *   oficial ahora se obtiene a través de las inscripciones.
     * - estado: situación del alumno, ver la constante ESTADOS.
     * - observaciones: notas libres.
     */
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

    /** Convertimos la fecha de nacimiento a objeto de fecha (por ejemplo, para calcular la edad). */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    /**
     * Estados posibles del estudiante dentro de la institución.
     * No borramos a los alumnos que se van: cambiamos su estado para conservar su historial.
     */
    public const ESTADOS = [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
        'retirado' => 'Retirado',
        'trasladado' => 'Trasladado',
        'graduado' => 'Graduado',
    ];

    /**
     * Relación "pertenece a" con el curso mediante el campo antiguo `curso_id`.
     * Se mantiene por compatibilidad; lo recomendable es usar cursoActual().
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación "uno a muchos" con las inscripciones del estudiante.
     * Cada inscripción corresponde a una gestión, por lo que aquí está su historial académico.
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * Busca la inscripción del estudiante en una gestión determinada.
     * Si no se indica la gestión, se toma la gestión actual del sistema.
     *
     * @return Inscripcion|null La inscripción encontrada (con su curso cargado) o null si no está inscrito.
     */
    public function inscripcionEn(?Gestion $gestion = null): ?Inscripcion
    {
        // Si no recibimos gestión usamos la actual; si tampoco existe, no hay inscripción que buscar.
        $gestion ??= Gestion::actual();
        if (! $gestion) {
            return null;
        }

        // Cargamos el curso junto con la inscripción para evitar una consulta adicional después.
        return $this->inscripciones()
            ->where('gestion_id', $gestion->id)
            ->with('curso')
            ->first();
    }

    /**
     * Devuelve el curso actual del estudiante.
     * Primero lo buscamos en la inscripción de la gestión vigente; si no la hay
     * (datos anteriores a la migración), recurrimos al campo antiguo `curso_id`.
     */
    public function cursoActual(): ?Curso
    {
        return $this->inscripcionEn()?->curso ?? $this->curso;
    }

    /**
     * Relación "muchos a muchos" con los responsables familiares (padre, madre o tutor).
     * Un alumno puede tener varios responsables y un responsable puede tener
     * varios hijos en el colegio. En la tabla pivote guardamos el parentesco y
     * si es el responsable principal.
     */
    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'estudiante_padre', 'estudiante_id', 'padre_id')
            ->withPivot('parentesco', 'es_principal')
            ->withTimestamps();
    }

    /**
     * Alias de responsables(). Lo conservamos porque algunas vistas y
     * controladores antiguos todavía usan el nombre "padres".
     */
    public function padres(): BelongsToMany
    {
        return $this->responsables();
    }

    /**
     * Relación "uno a muchos" con los registros de asistencia del estudiante.
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Relación "uno a muchos" con las salidas autorizadas del estudiante durante la jornada.
     */
    public function salidas(): HasMany
    {
        return $this->hasMany(SalidaEstudiante::class);
    }

    /**
     * Relación "uno a muchos" con las incidencias registradas sobre el estudiante.
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }

    /**
     * Relación "uno a muchos" con las citaciones emitidas a la familia del estudiante.
     */
    public function citaciones(): HasMany
    {
        return $this->hasMany(Citacion::class);
    }

    /**
     * Relación "uno a muchos" con las cuotas de aporte del alumno.
     * La obligación de pago se registra a nombre del alumno y no del padre:
     * así, si ambos padres tienen cuenta, la cuota no se duplica.
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaAporte::class);
    }

    /**
     * Relación "uno a muchos" con los avisos dirigidos a los responsables de este alumno
     * (avisos de alcance "familia").
     */
    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class);
    }

    /**
     * Devuelve el nombre completo con el formato "Apellidos Nombres", que es el
     * orden habitual en las listas escolares.
     */
    public function nombreCompleto(): string
    {
        return trim("{$this->apellidos} {$this->nombres}");
    }

    /**
     * Indica si el usuario dado es uno de los responsables vinculados a este alumno.
     * Lo usamos para validar que un padre solo pueda ver la información de sus propios hijos.
     */
    public function esRepresentadoPor(?User $user): bool
    {
        // Sin usuario autenticado no puede existir vínculo.
        if (! $user) {
            return false;
        }

        return $this->responsables()->whereKey($user->getKey())->exists();
    }
}
