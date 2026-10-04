<?php

namespace App\Services;

use App\Models\CalendarioExcepcion;
use App\Models\Curso;
use App\Models\HorarioCurso;
use Illuminate\Support\Carbon;

/**
 * Calendario escolar aplicado a la asistencia.
 *
 * Esta clase responde a una pregunta clave para el control de asistencia:
 * ¿el curso tenía clases en tal fecha y turno? Para responderla combina el
 * horario semanal de cada curso con las excepciones del calendario
 * (feriados y jornadas sin clases).
 *
 * Se utiliza desde AsistenciaController, para impedir que se registre
 * asistencia en un día sin clases y para mostrar el motivo, y desde
 * EstadisticaAsistencia, para calcular cuántos días hábiles hubo en un
 * período (el denominador de los porcentajes).
 *
 * Reglas que seguimos:
 * - Un día "sin clases programadas" NO es una ausencia del alumno.
 * - Un día "sin registro" tampoco es ausencia: solo significa que nadie
 *   cargó la asistencia ese día.
 * - No se calcula inasistencia en la tarde si el curso no tiene clases en
 *   ese turno.
 * - El calendario es el que define el denominador de los reportes.
 *
 * Vigencia de horarios: cada horario tiene una fecha "vigente desde" (y,
 * cuando se desactiva, una "vigente hasta"). Gracias a esto, cambiar un
 * horario hacia el futuro no altera la interpretación de las asistencias
 * pasadas: para cada fecha solo cuentan los horarios que estaban vigentes
 * ese día.
 */
final class CalendarioAsistencia
{
    /**
     * Indica si el curso tiene clases programadas en una fecha y turno.
     *
     * Primero revisa si ese día hay una excepción del calendario (feriado o
     * jornada sin clases) para el curso o para todo el colegio; si la hay, no
     * hay clases. Si no la hay, busca un horario semanal del curso para ese
     * día de la semana y turno que estuviera vigente en esa fecha.
     *
     * @param  string|Carbon  $fecha  Fecha a consultar.
     * @param  string  $turno  Turno (por ejemplo, mañana o tarde).
     * @return bool true si había clases programadas.
     */
    public static function hayClases(Curso $curso, string|Carbon $fecha, string $turno): bool
    {
        // Aceptamos la fecha como texto o como objeto Carbon.
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        // Paso 1: buscamos una excepción del calendario para ese día. Una
        // excepción sin curso (curso_id nulo) afecta a todo el colegio; una con
        // curso solo afecta a ese curso.
        $excepcion = CalendarioExcepcion::query()
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('tipo', [CalendarioExcepcion::TIPO_SIN_CLASES, CalendarioExcepcion::TIPO_FERIADO])
            ->where(fn ($q) => $q->whereNull('curso_id')->orWhere('curso_id', $curso->id))
            ->exists();

        if ($excepcion) {
            return false;
        }

        // Paso 2: buscamos un horario semanal del curso para ese día de la
        // semana (1 = lunes ... 7 = domingo) y turno, que estuviera vigente en
        // la fecha consultada, es decir, dentro del rango [vigente_desde, vigente_hasta].
        // Cuando se desactiva un horario se guarda vigente_hasta con la fecha
        // de ese día; así las fechas pasadas se siguen interpretando con el
        // horario que regía entonces y no se reinterpretan asistencias
        // anteriores. Un horario inactivo que NO tiene vigente_hasta nunca
        // estuvo en uso (se creó ya inactivo), por eso no se toma en cuenta.
        return HorarioCurso::query()
            ->where('curso_id', $curso->id)
            ->where('turno', $turno)
            ->where('dia_semana', $fecha->dayOfWeekIso)
            ->where(function ($q) {
                $q->where('activo', true)
                    ->orWhereNotNull('vigente_hasta');
            })
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $fecha->endOfDay()))
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha->startOfDay()))
            ->exists();
    }

    /**
     * Devuelve la excepción del calendario que aplica a un curso en una
     * fecha, si existe.
     *
     * Sirve para explicarle al usuario por qué no hay clases ese día (por
     * ejemplo, "Feriado" o "Jornada pedagógica").
     */
    public static function excepcionDelDia(Curso $curso, string|Carbon $fecha): ?CalendarioExcepcion
    {
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return CalendarioExcepcion::query()
            ->whereDate('fecha', $fecha->toDateString())
            ->where(fn ($q) => $q->whereNull('curso_id')->orWhere('curso_id', $curso->id))
            // Si hay una excepción propia del curso y otra general, damos
            // prioridad a la del curso: "curso_id is null" vale 0 para las del
            // curso y 1 para las generales, así que las del curso salen primero.
            ->orderByRaw('curso_id is null')
            ->first();
    }

    /**
     * Calcula los días con clases entre dos fechas para un curso y turno.
     *
     * Recorre el rango día por día y se queda con los días en que
     * hayClases() devuelve verdadero. La cantidad de días resultante es el
     * denominador que usan los reportes de asistencia, de modo que los
     * feriados, fines de semana y días sin clases no bajan el porcentaje de
     * asistencia de los alumnos.
     *
     * @return array<int, string> Lista de fechas con clases en formato Y-m-d.
     */
    public static function diasHabiles(Curso $curso, string|Carbon $desde, string|Carbon $hasta, string $turno): array
    {
        // Normalizamos ambas fechas al inicio del día para comparar solo fechas.
        $desde = ($desde instanceof Carbon ? $desde : Carbon::parse($desde))->startOfDay();
        $hasta = ($hasta instanceof Carbon ? $hasta : Carbon::parse($hasta))->startOfDay();

        // Recorremos día por día desde la fecha inicial hasta la final
        // (inclusive). Usamos copy() para no modificar la fecha original.
        $dias = [];
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            if (self::hayClases($curso, $d, $turno)) {
                $dias[] = $d->toDateString();
            }
        }

        return $dias;
    }
}
