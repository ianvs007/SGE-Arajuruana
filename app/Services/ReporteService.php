<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Support\Dinero;
use Illuminate\Support\Collection;

/**
 * Fuente ÚNICA de datos para los reportes del sistema.
 *
 * Una exigencia del colegio es que los totales que se ven en pantalla, en
 * el PDF y en el Excel sean IDÉNTICOS. Para garantizarlo, las tres salidas
 * usan los mismos arreglos que se generan aquí, y este servicio, a su vez,
 * reutiliza la lógica que ya existe en otras clases:
 * - Asistencia: EstadisticaAsistencia, que usa como denominador los días
 *   con clases y distingue "sin registro" de "ausente".
 * - Aportes: las cuotas (CuotaAporte) sumadas en centavos enteros con
 *   Dinero, y AporteService::estadoDeCuenta() para el detalle por alumno.
 *
 * Este servicio no hace cálculos propios con fórmulas distintas: si cambia
 * una regla de negocio, se cambia en su servicio original y el reporte la
 * hereda automáticamente.
 *
 * Se utiliza desde ReporteController (pantallas, PDF y descargas Excel) y
 * desde las clases de exportación AportePorCursoExport y
 * AportePorAlumnoExport (método aNumero()).
 */
final class ReporteService
{
    /**
     * Prepara los datos del reporte de asistencia de un curso, turno y
     * rango de fechas.
     *
     * Devuelve las mismas filas y totales que muestra el reporte de
     * asistencia en pantalla, junto con los filtros usados para que la
     * vista o el PDF puedan mostrarlos en el encabezado.
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
     * Resumen económico por CURSO de una gestión.
     *
     * Para cada curso calcula lo emitido, lo recaudado (pagado), lo vencido
     * y el saldo pendiente, todo en centavos, con el mismo cálculo que la
     * pantalla de cuotas. Las cuotas se agrupan por el curso de la
     * inscripción del alumno. Las cuotas exentas no se cuentan, igual que en
     * pantalla.
     *
     * @param  Gestion|null  $gestion  Gestión a reportar; si es null se toman todas las cuotas.
     * @return array{gestion: ?Gestion, filas: Collection, totales: array, hoy: string}
     */
    public static function aportePorCurso(?Gestion $gestion): array
    {
        // La fecha de hoy define qué cuotas se consideran vencidas.
        $hoy = now()->toDateString();

        // Traemos todas las cuotas de la gestión cargando de una vez el alumno
        // y la inscripción con sus cursos, para no hacer una consulta por cuota.
        $cuotas = CuotaAporte::with(['estudiante.curso', 'inscripcion.curso'])
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion?->id))
            ->get();

        // Agrupamos por el curso de la inscripción. Mientras dure la transición
        // algunos alumnos todavía tienen el curso guardado directamente en su
        // ficha, así que usamos ese como respaldo; si no hay ninguno, se agrupan
        // en el "curso 0" (sin curso asignado).
        $porCurso = $cuotas->groupBy(function (CuotaAporte $cuota) {
            return $cuota->inscripcion?->curso_id ?? $cuota->estudiante?->curso_id ?? 0;
        });

        $filas = collect();
        $totales = ['emitido' => 0, 'pagado' => 0, 'saldo' => 0, 'vencido' => 0, 'alumnos' => 0, 'cuotas_vencidas' => 0];

        foreach ($porCurso as $cursoId => $delCurso) {
            // Buscamos el objeto Curso para mostrar su nombre: tomamos el de la
            // primera cuota del grupo que lo tenga (por inscripción o por la
            // ficha del alumno).
            $curso = null;
            foreach ($delCurso as $c) {
                $curso = $c->inscripcion?->curso ?? $c->estudiante?->curso;
                if ($curso) {
                    break;
                }
            }

            // Fila del curso. La cantidad de alumnos cuenta estudiantes
            // distintos con al menos una cuota no exenta.
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

            // Sumamos en centavos los montos de cada cuota del curso, saltando
            // las exentas. Si la cuota está vencida, su saldo también suma a lo vencido.
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

            // Acumulamos los valores del curso en los totales generales.
            $totales['emitido'] += $fila['emitido'];
            $totales['pagado'] += $fila['pagado'];
            $totales['saldo'] += $fila['saldo'];
            $totales['vencido'] += $fila['vencido'];
            $totales['alumnos'] += $fila['alumnos'];
            $totales['cuotas_vencidas'] += $fila['cuotas_vencidas'];

            $filas->push($fila);
        }

        // Ordenamos los cursos alfabéticamente por su nombre.
        $filas = $filas->sortBy([['nombre', 'asc']])->values();

        return ['gestion' => $gestion, 'filas' => $filas, 'totales' => $totales, 'hoy' => $hoy];
    }

    /**
     * Estado de cuenta POR ALUMNO de una gestión.
     *
     * Para cada alumno llama a AporteService::estadoDeCuenta(), que es el
     * mismo método que usa la pantalla de estado de cuenta, así que los
     * números siempre coinciden. Devuelve una fila por alumno con sus
     * totales en centavos y los totales generales.
     *
     * @param  Gestion|null  $gestion  Gestión a reportar.
     * @param  Collection<int, Estudiante>|null  $estudiantes  Filtro opcional, por ejemplo los alumnos de un curso.
     * @return array{gestion: ?Gestion, filas: Collection, totales: array, hoy: string}
     */
    public static function aportePorAlumno(?Gestion $gestion, ?Collection $estudiantes = null): array
    {
        $hoy = now()->toDateString();

        // Si no se pasó una lista de alumnos, tomamos los alumnos activos que
        // tienen cuotas en la gestión, ordenados por apellidos y nombres.
        $alumnos = $estudiantes ?? Estudiante::query()
            ->where('estado', 'activo')
            ->whereHas('cuotas', fn ($q) => $q->when($gestion, fn ($q2) => $q2->where('gestion_id', $gestion?->id)))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();

        $filas = collect();
        $totales = ['emitido' => 0, 'pagado' => 0, 'saldo' => 0, 'vencido' => 0, 'cuotas_vencidas' => 0];

        foreach ($alumnos as $estudiante) {
            // Reutilizamos el cálculo oficial del estado de cuenta.
            $estado = AporteService::estadoDeCuenta($estudiante, $gestion);
            $t = $estado['totales'];

            // Los alumnos sin cuotas emitidas en la gestión no aparecen, porque
            // no tienen deuda que reportar.
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

            // Sumamos los totales del alumno a los totales generales.
            $totales['emitido'] += $t['emitido'];
            $totales['pagado'] += $t['pagado'];
            $totales['saldo'] += $t['saldo'];
            $totales['vencido'] += $t['vencido'];
            $totales['cuotas_vencidas'] += $t['cuotas_pendientes'];
        }

        return ['gestion' => $gestion, 'filas' => $filas, 'totales' => $totales, 'hoy' => $hoy];
    }

    /**
     * Convierte centavos a un número decimal para las celdas de Excel.
     *
     * En Excel necesitamos números reales (no texto con "Bs") para que el
     * usuario pueda sumar o filtrar. Esta es la única conversión a decimal
     * que hacemos, y solo al final, para mostrar; todos los cálculos previos
     * se hicieron en centavos enteros.
     */
    public static function aNumero(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    /** Devuelve el monto como texto "Bs 1.234,56", con el mismo formato que la pantalla (usa Dinero). */
    public static function formatoBs(int $centavos): string
    {
        return Dinero::formato($centavos);
    }
}
