<?php

namespace App\Support;

use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alcance de los datos según el rol del usuario.
 *
 * Los permisos indican QUÉ puede hacer un usuario, pero no SOBRE QUIÉNES.
 * Esta clase resuelve la segunda parte: decide qué estudiantes puede ver u
 * operar cada persona.
 *
 * - Administración, Dirección, Coordinación y Subdirección tienen alcance
 *   institucional, es decir, ven a todos los estudiantes.
 * - El Docente solo ve a los alumnos de los cursos que tiene asignados en
 *   la gestión actual.
 * - El Responsable familiar solo ve a sus representados.
 *
 * Toda consulta y exportación de datos de estudiantes debe pasar por aquí, y
 * si el rol no se reconoce el acceso se niega por defecto. La usan, entre
 * otros, EstudianteController, CitacionController, AsistenciaController,
 * SalidaEstudianteController, IncidenciaController, AvisoController,
 * HistorialEstudianteController, CuotaAporteController, ReporteController y
 * DashboardController.
 */
final class Alcance
{
    /**
     * Devuelve una consulta de los estudiantes que el usuario puede ver.
     *
     * Se devuelve un Builder (y no una colección) para que el controlador
     * pueda seguir agregando filtros, ordenamientos o paginación sobre la
     * consulta ya restringida.
     */
    public static function estudiantes(User $user): Builder
    {
        $query = Estudiante::query();

        // Los roles con alcance institucional ven a todos sin restricciones.
        if ($user->tieneAlcanceInstitucional()) {
            return $query;
        }

        if ($user->esDocente()) {
            $cursoIds = self::cursoIdsDocente($user);

            // Durante la transición conviven dos formas de saber el curso de un
            // alumno: el campo curso_id del estudiante y sus inscripciones
            // activas. Aceptamos cualquiera de las dos para no dejar a nadie fuera.
            return $query->where(function (Builder $q) use ($cursoIds) {
                $q->whereIn('curso_id', $cursoIds)
                    ->orWhereHas('inscripciones', function (Builder $i) use ($cursoIds) {
                        $i->whereIn('curso_id', $cursoIds)->where('estado', 'activa');
                    });
            });
        }

        // El responsable familiar solo ve a los estudiantes vinculados con él.
        if ($user->esResponsableFamiliar()) {
            return $query->whereHas('responsables', fn (Builder $q) => $q->whereKey($user->getKey()));
        }

        // Si el rol no se reconoce, negamos el acceso con una condición que
        // siempre es falsa, de modo que la consulta no devuelva ningún registro.
        return $query->whereRaw('1 = 0');
    }

    /**
     * Obtiene los IDs de los cursos asignados a un docente.
     *
     * Si hay una gestión (año escolar) marcada como actual, filtramos por
     * ella para que el docente no siga viendo cursos de años anteriores; si
     * no la hay, devolvemos todas sus asignaciones.
     *
     * @return array Lista de IDs de curso.
     */
    public static function cursoIdsDocente(User $user): array
    {
        $gestion = Gestion::actual();

        // La asignación docente-curso guarda la gestión en la tabla intermedia.
        $relacion = $user->cursosAsignados();
        if ($gestion) {
            $relacion->wherePivot('gestion_id', $gestion->id);
        }

        return $relacion->pluck('cursos.id')->all();
    }

    /**
     * Indica si el usuario puede ver u operar sobre un estudiante concreto.
     *
     * Aplica las mismas reglas que estudiantes(), pero para un solo registro.
     * Los controladores la usan junto con abort_unless() para responder con
     * un error 403 cuando alguien intenta abrir un estudiante ajeno
     * cambiando el ID en la dirección web.
     */
    public static function puedeVerEstudiante(User $user, Estudiante $estudiante): bool
    {
        if ($user->tieneAlcanceInstitucional()) {
            return true;
        }

        // El responsable familiar solo puede ver a quienes representa.
        if ($user->esResponsableFamiliar()) {
            return $user->representaA($estudiante);
        }

        // El docente puede verlo si el alumno pertenece a uno de sus cursos,
        // ya sea por el curso_id directo o por una inscripción activa.
        if ($user->esDocente()) {
            $cursoIds = self::cursoIdsDocente($user);

            return in_array($estudiante->curso_id, $cursoIds, true)
                || $estudiante->inscripciones()
                    ->whereIn('curso_id', $cursoIds)
                    ->where('estado', 'activa')
                    ->exists();
        }

        // Cualquier otro caso queda denegado por defecto.
        return false;
    }
}
