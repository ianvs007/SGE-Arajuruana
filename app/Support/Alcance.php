<?php

namespace App\Support;

use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alcance de datos por rol (§5, §6, §18).
 *
 * - Administración/Dirección/Coordinación/Subdirección: alcance institucional.
 * - Docente: solo alumnos de sus cursos asignados en la gestión actual.
 * - Responsable familiar: solo sus representados.
 *
 * Toda consulta y exportación debe pasar por aquí; el acceso se deniega por defecto.
 */
final class Alcance
{
    /** Query de estudiantes visibles para el usuario dado. */
    public static function estudiantes(User $user): Builder
    {
        $query = Estudiante::query();

        if ($user->tieneAlcanceInstitucional()) {
            return $query;
        }

        if ($user->esDocente()) {
            $cursoIds = self::cursoIdsDocente($user);

            // Transición: curso vía inscripciones de la gestión actual o curso_id directo.
            return $query->where(function (Builder $q) use ($cursoIds) {
                $q->whereIn('curso_id', $cursoIds)
                    ->orWhereHas('inscripciones', function (Builder $i) use ($cursoIds) {
                        $i->whereIn('curso_id', $cursoIds)->where('estado', 'activa');
                    });
            });
        }

        if ($user->esResponsableFamiliar()) {
            return $query->whereHas('responsables', fn (Builder $q) => $q->whereKey($user->getKey()));
        }

        // Denegado por defecto: sin rol reconocido no ve nada.
        return $query->whereRaw('1 = 0');
    }

    /** IDs de curso del docente en la gestión actual (o todas si no hay gestión actual). */
    public static function cursoIdsDocente(User $user): array
    {
        $gestion = Gestion::actual();

        $relacion = $user->cursosAsignados();
        if ($gestion) {
            $relacion->wherePivot('gestion_id', $gestion->id);
        }

        return $relacion->pluck('cursos.id')->all();
    }

    /** ¿Puede el usuario ver/operar sobre este estudiante en concreto? */
    public static function puedeVerEstudiante(User $user, Estudiante $estudiante): bool
    {
        if ($user->tieneAlcanceInstitucional()) {
            return true;
        }

        if ($user->esResponsableFamiliar()) {
            return $user->representaA($estudiante);
        }

        if ($user->esDocente()) {
            $cursoIds = self::cursoIdsDocente($user);

            return in_array($estudiante->curso_id, $cursoIds, true)
                || $estudiante->inscripciones()
                    ->whereIn('curso_id', $cursoIds)
                    ->where('estado', 'activa')
                    ->exists();
        }

        return false;
    }
}
