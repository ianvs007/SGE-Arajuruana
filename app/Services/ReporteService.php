<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Support\Dinero;
use Illuminate\Support\Collection;

/**
 * Fuente ÚNICA de datos para reportes (§16).
 *
 * Regla confirmada: los totales de pantalla, PDF y Excel deben ser IDÉNTICOS.
 * Para garantizarlo, las tres salidas consumen los mismos arreglos generados
 * aquí, que a su vez reutilizan la lógica canónica:
 * - Asistencia: `EstadisticaAsistencia` (denominador explícito de días hábiles,
 *   "sin registro" ≠ "ausente", §9).
 * - Aporte: `CuotaAporte` en centavos enteros vía `Dinero` y
 *   `AporteService::estadoDeCuenta()` por alumno (§14).
 *
 * Nada de este servicio consulta la BD por su cuenta con fórmulas distintas:
 * si cambia una regla de negocio, cambia en su servicio canónico y el reporte
 * la hereda automáticamente.
 */
final class ReporteService
{
    /**
     * Asistencia por curso/turno/rango — mismas filas y totales que
     * `AsistenciaController::reporte()` (pantalla).
     *
     * @return array{curso: Curso, turno: string, desde: string, hasta: string, filas: array, totales: array}
     */
    public static function asistenciaCurso(Curso $curso, string $turno, string $desde, string $hasta): array
    {
        return [
            'curso' => $curso,
            'turno' => $turno,
            'desde' => $desde,
            'hasta' => $hasta,
            'filas' => EstadisticaAsistencia::porCurso($curso, $turno, $desde, $hasta),
            'totales' => EstadisticaAsistencia::totalesCurso($curso, $turno, $desde, $hasta),
        ];
    }

    /**
     * Resumen económico por CURSO de una gestión (§16): emitido, recaudado,
     * vencido y saldo — mismo cálculo en centavos que `CuotaAporteController::index()`
     * (pantalla de cuotas), agrupado por curso vía inscripciones.
     *
     * Las cuotas exentas no cuentan (igual que en pantalla).
     *
     * @return array{gestion: ?Gestion, filas: Collection, totales: array, hoy: string}
     */
    public static function aportePorCurso(?Gestion $gestion): array
    {
        $hoy = now()->toDateString();

        $cuotas = CuotaAporte::with(['estudiante.curso', 'inscripcion.curso'])
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion?->id))
            ->get();

        // Agrupa por curso de la inscripción (o curso directo del alumno en transición).
        $porCurso = $cuotas->groupBy(function (CuotaAporte $cuota) {
            return $cuota->inscripcion?->curso_id ?? $cuota->estudiante?->curso_id ?? 0;
        });

        $filas = collect();
        $totales = ['emitido' => 0, 'pagado' => 0, 'saldo' => 0, 'vencido' => 0, 'alumnos' => 0, 'cuotas_vencidas' => 0];

        foreach ($porCurso as $cursoId => $delCurso) {
            // Curso de la inscripción (o curso directo del alumno en transición).
            $curso = null;
            foreach ($delCurso as $c) {
                $curso = $c->inscripcion?->curso ?? $c->estudiante?->curso;
                if ($curso) {
                    break;
                }
            }

            $fila = [
                'curso_id' => $cursoId,
                'curso' => $curso,
                'nombre' => $curso?->etiqueta() ?? 'Sin curso asignado',
                'alumnos' => $delCurso->where('estado', '!=', 'exenta')->pluck('estudiante_id')->unique()->count(),
                'emitido' => 0,
                'pagado' => 0,
                'saldo' => 0,
                'vencido' => 0,
                'cuotas_vencidas' => 0,
            ];

            foreach ($delCurso as $cuota) {
                if ($cuota->estado === 'exenta') {
                    continue;
                }
                $fila['emitido'] += $cuota->montoCentavos();
                $fila['pagado'] += $cuota->pagadoCentavos();
                $fila['saldo'] += $cuota->saldoCentavos();
                if ($cuota->estaVencida($hoy)) {
                    $fila['vencido'] += $cuota->saldoCentavos();
                    $fila['cuotas_vencidas']++;
                }
            }

            $totales['emitido'] += $fila['emitido'];
            $totales['pagado'] += $fila['pagado'];
            $totales['saldo'] += $fila['saldo'];
            $totales['vencido'] += $fila['vencido'];
            $totales['alumnos'] += $fila['alumnos'];
            $totales['cuotas_vencidas'] += $fila['cuotas_vencidas'];

            $filas->push($fila);
        }

        $filas = $filas->sortBy([['nombre', 'asc']])->values();

        return ['gestion' => $gestion, 'filas' => $filas, 'totales' => $totales, 'hoy' => $hoy];
    }

    /**
     * Estado de cuenta POR ALUMNO de una gestión (§16): reutiliza
     * `AporteService::estadoDeCuenta()` — idéntico a la pantalla de estado de
     * cuenta del alumno. Un alumno por fila con totales en centavos.
     *
     * @param  Collection<int, Estudiante>|null  $estudiantes  filtro opcional (p. ej. un curso)
     * @return array{gestion: ?Gestion, filas: Collection, totales: array, hoy: string}
     */
    public static function aportePorAlumno(?Gestion $gestion, ?Collection $estudiantes = null): array
    {
        $hoy = now()->toDateString();

        $alumnos = $estudiantes ?? Estudiante::query()
            ->where('estado', 'activo')
            ->whereHas('cuotas', fn ($q) => $q->when($gestion, fn ($q2) => $q2->where('gestion_id', $gestion?->id)))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();

        $filas = collect();
        $totales = ['emitido' => 0, 'pagado' => 0, 'saldo' => 0, 'vencido' => 0, 'cuotas_vencidas' => 0];

        foreach ($alumnos as $estudiante) {
            $estado = AporteService::estadoDeCuenta($estudiante, $gestion);
            $t = $estado['totales'];

            // Alumnos sin cuotas emitidas en la gestión no aparecen (no hay deuda que reportar).
            if ($t['emitido'] === 0) {
                continue;
            }

            $filas->push([
                'estudiante_id' => $estudiante->id,
                'estudiante' => $estudiante,
                'codigo' => $estudiante->codigo,
                'nombre' => $estudiante->nombreCompleto(),
                'curso' => $estudiante->cursoActual()?->etiqueta(),
                'emitido' => $t['emitido'],
                'pagado' => $t['pagado'],
                'saldo' => $t['saldo'],
                'vencido' => $t['vencido'],
                'cuotas_vencidas' => $t['cuotas_pendientes'],
            ]);

            $totales['emitido'] += $t['emitido'];
            $totales['pagado'] += $t['pagado'];
            $totales['saldo'] += $t['saldo'];
            $totales['vencido'] += $t['vencido'];
            $totales['cuotas_vencidas'] += $t['cuotas_pendientes'];
        }

        return ['gestion' => $gestion, 'filas' => $filas, 'totales' => $totales, 'hoy' => $hoy];
    }

    /** Número de centavos a decimal para celdas numéricas de Excel (sin formato). */
    public static function aNumero(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    /** Etiqueta legible "Bs 1.234,56" (idéntica a pantalla, vía Dinero). */
    public static function formatoBs(int $centavos): string
    {
        return Dinero::formato($centavos);
    }
}
