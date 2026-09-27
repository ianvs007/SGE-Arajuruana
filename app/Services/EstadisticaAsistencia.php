<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Curso;
use Illuminate\Support\Carbon;

/**
 * Estadísticas de asistencia con denominador explícito (§9).
 *
 * Separación obligatoria de tres situaciones distintas:
 * - Ausencia: hay registro con estado ausente.
 * - Falta de registro: había clases pero nadie registró el estado.
 * - Jornada no aplicable: sin clases programadas (feriado, jornada libre,
 *   turno sin clases). NO cuenta en el denominador.
 *
 * Nunca se confunde "sin registro" con "ausente".
 */
final class EstadisticaAsistencia
{
    /**
     * Resumen por alumno para un curso, turno y rango de fechas.
     *
     * @return array<int, array> lista por estudiante con conteos y porcentaje
     */
    public static function porCurso(Curso $curso, string $turno, string|Carbon $desde, string|Carbon $hasta): array
    {
        $dias = CalendarioAsistencia::diasHabiles($curso, $desde, $hasta, $turno);
        $totalDias = count($dias);

        $inscripciones = $curso->inscripciones()
            ->where('gestion_id', $curso->gestion_id)
            ->where('estado', 'activa')
            ->with('estudiante')
            ->get();

        // whereBetween + filtro por fecha normalizada: compatible con el
        // almacenamiento date (MySQL) y datetime (SQLite en pruebas).
        $diasSet = array_flip($dias);
        $registros = Asistencia::query()
            ->where('curso_id', $curso->id)
            ->where('turno', $turno)
            ->whereBetween('fecha', [
                Carbon::parse($desde)->startOfDay(),
                Carbon::parse($hasta)->endOfDay(),
            ])
            ->whereIn('estudiante_id', $inscripciones->pluck('estudiante_id'))
            ->get()
            ->filter(fn ($r) => isset($diasSet[$r->fecha->toDateString()]))
            ->groupBy('estudiante_id');

        return $inscripciones->map(function ($inscripcion) use ($registros, $totalDias) {
            $delAlumno = $registros->get($inscripcion->estudiante_id, collect());

            $conteo = [
                'presente' => 0,
                'atrasado' => 0,
                'justificada' => 0,
                'ausente' => 0,
            ];
            foreach ($delAlumno as $registro) {
                if (array_key_exists($registro->estado, $conteo)) {
                    $conteo[$registro->estado]++;
                }
            }

            $sinRegistro = max(0, $totalDias - $delAlumno->count());

            return [
                'inscripcion' => $inscripcion,
                'estudiante' => $inscripcion->estudiante,
                'dias_habiles' => $totalDias,
                'conteo' => $conteo,
                'sin_registro' => $sinRegistro,
                // Denominador explícito: solo días con clases del rango (§9).
                'porcentaje_asistencia' => $totalDias > 0
                    ? round((($conteo['presente'] + $conteo['atrasado'] + $conteo['justificada']) / $totalDias) * 100, 1)
                    : null,
            ];
        })->values()->all();
    }

    /** Totales agregados del curso (para encabezados de reporte, §16). */
    public static function totalesCurso(Curso $curso, string $turno, string|Carbon $desde, string|Carbon $hasta): array
    {
        $filas = self::porCurso($curso, $turno, $desde, $hasta);
        $dias = CalendarioAsistencia::diasHabiles($curso, $desde, $hasta, $turno);

        $sum = fn (string $estado) => collect($filas)->sum(fn ($f) => $f['conteo'][$estado]);

        return [
            'dias_habiles' => count($dias),
            'alumnos' => count($filas),
            'presente' => $sum('presente'),
            'atrasado' => $sum('atrasado'),
            'justificada' => $sum('justificada'),
            'ausente' => $sum('ausente'),
            'sin_registro' => collect($filas)->sum('sin_registro'),
        ];
    }
}
