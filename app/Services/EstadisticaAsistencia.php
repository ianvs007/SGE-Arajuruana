<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Curso;
use Illuminate\Support\Carbon;

/**
 * Cálculo de estadísticas de asistencia por curso.
 *
 * Genera, para un curso, turno y rango de fechas, el resumen de asistencia
 * de cada alumno (presentes, atrasos, faltas justificadas, ausencias, días
 * sin registro y porcentaje) y los totales del curso. Se utiliza desde
 * AsistenciaController (reporte en pantalla) y desde ReporteService, que a
 * su vez alimenta los reportes en PDF y Excel, para que todos muestren las
 * mismas cifras.
 *
 * Una decisión importante del diseño es distinguir tres situaciones que a
 * simple vista podrían parecer iguales:
 * - Ausencia: existe un registro con estado "ausente".
 * - Falta de registro: había clases, pero nadie cargó la asistencia.
 * - Jornada no aplicable: no había clases (feriado, jornada libre o turno
 *   sin clases). Estos días NO cuentan en el denominador.
 *
 * Así nunca se confunde "sin registro" con "ausente", lo que sería injusto
 * para el alumno.
 */
final class EstadisticaAsistencia
{
    /**
     * Calcula el resumen de asistencia de cada alumno de un curso.
     *
     * Primero obtiene los días con clases del período (el denominador),
     * luego trae los registros de asistencia de los alumnos inscritos y, por
     * cada alumno, cuenta cuántos registros tiene de cada estado. Los días
     * con clases que no tienen registro se cuentan como "sin registro". El
     * porcentaje considera como asistencia a los presentes, los atrasados y
     * las faltas justificadas.
     *
     * @param  string  $turno  Turno a consultar.
     * @param  string|Carbon  $desde  Fecha inicial del rango.
     * @param  string|Carbon  $hasta  Fecha final del rango.
     * @return array<int, array> Una fila por estudiante con sus conteos y su porcentaje.
     */
    public static function porCurso(Curso $curso, string $turno, string|Carbon $desde, string|Carbon $hasta): array
    {
        // Días con clases del período: este es el denominador del porcentaje.
        $dias = CalendarioAsistencia::diasHabiles($curso, $desde, $hasta, $turno);
        $totalDias = count($dias);

        // Alumnos con inscripción activa en el curso para su gestión.
        $inscripciones = $curso->inscripciones()
            ->where('gestion_id', $curso->gestion_id)
            ->where('estado', 'activa')
            ->with('estudiante')
            ->get();

        // Traemos los registros del rango con whereBetween (desde el inicio del
        // primer día hasta el final del último) y luego filtramos en PHP
        // comparando solo la fecha. Lo hicimos así porque en MySQL la columna
        // es de tipo date, pero en las pruebas con SQLite se guarda con hora, y
        // de esta forma funciona igual en ambos casos. Además descartamos los
        // registros de días sin clases, que no deben contar.
        // array_flip convierte la lista de días en un índice para buscar rápido con isset().
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

        // Armamos una fila por alumno inscrito.
        return $inscripciones->map(function ($inscripcion) use ($registros, $totalDias) {
            // Registros de este alumno (colección vacía si no tiene ninguno).
            $delAlumno = $registros->get($inscripcion->estudiante_id, collect());

            // Contamos cuántos registros tiene de cada estado. Si apareciera un
            // estado desconocido, simplemente no se cuenta.
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

            // Días con clases que no tienen registro. Usamos max(0, ...) para
            // que el resultado nunca sea negativo.
            $sinRegistro = max(0, $totalDias - $delAlumno->count());

            return [
                'inscripcion' => $inscripcion,
                'estudiante' => $inscripcion->estudiante,
                'dias_habiles' => $totalDias,
                'conteo' => $conteo,
                'sin_registro' => $sinRegistro,
                // Porcentaje de asistencia = (presentes + atrasados + justificadas)
                // / días con clases x 100, redondeado a un decimal. Si en el
                // período no hubo clases devolvemos null en lugar de dividir entre cero.
                'porcentaje_asistencia' => $totalDias > 0
                    ? round((($conteo['presente'] + $conteo['atrasado'] + $conteo['justificada']) / $totalDias) * 100, 1)
                    : null,
            ];
        })->values()->all();
    }

    /**
     * Calcula los totales de asistencia de todo el curso.
     *
     * Se usa en los encabezados de los reportes. Suma los conteos de cada
     * estado de todos los alumnos que devuelve porCurso(), junto con la
     * cantidad de días hábiles y de alumnos.
     *
     * @return array Totales del curso por estado.
     */
    public static function totalesCurso(Curso $curso, string $turno, string|Carbon $desde, string|Carbon $hasta): array
    {
        $filas = self::porCurso($curso, $turno, $desde, $hasta);
        $dias = CalendarioAsistencia::diasHabiles($curso, $desde, $hasta, $turno);

        // Pequeña función auxiliar que suma un estado en todas las filas.
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
