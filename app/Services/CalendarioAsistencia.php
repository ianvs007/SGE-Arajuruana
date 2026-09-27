<?php

namespace App\Services;

use App\Models\CalendarioExcepcion;
use App\Models\Curso;
use App\Models\HorarioCurso;
use Illuminate\Support\Carbon;

/**
 * Calendario aplicable a la asistencia (§9).
 *
 * Reglas confirmadas:
 * - "Sin clases programadas" NO equivale a ausencia.
 * - "Sin registro" NO equivale a ausencia: es la ausencia de fila.
 * - No se calcula inasistencia vespertina si el curso no tiene clases esa tarde.
 * - El calendario rige el denominador de los reportes.
 *
 * Vigencia (§4): `horarios_curso.vigente_desde` permite que modificar un horario
 * futuro NO reinterprete asistencias pasadas: para una fecha dada solo cuentan
 * los horarios cuya vigencia ya había comenzado ese día.
 */
final class CalendarioAsistencia
{
    /**
     * ¿El curso tiene clases programadas en esa fecha y turno?
     * Considera horario semanal + excepciones del calendario.
     */
    public static function hayClases(Curso $curso, string|Carbon $fecha, string $turno): bool
    {
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        // 1) Excepción para el curso o para toda la gestión (feriado / sin clases).
        $excepcion = CalendarioExcepcion::query()
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('tipo', [CalendarioExcepcion::TIPO_SIN_CLASES, CalendarioExcepcion::TIPO_FERIADO])
            ->where(fn ($q) => $q->whereNull('curso_id')->orWhere('curso_id', $curso->id))
            ->exists();

        if ($excepcion) {
            return false;
        }

        // 2) Horario semanal vigente para ese día y turno.
        //    Ventana de vigencia [vigente_desde, vigente_hasta]: desactivar un
        //    horario fija vigente_hasta = hoy, de modo que las fechas pasadas
        //    siguen interpretándose con la configuración que estaba vigente
        //    entonces (§4/§20.5: no reinterpreta asistencias anteriores).
        //    Un horario inactivo SIN vigente_hasta nunca contó (creado inactivo).
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
     * Excepción del calendario que aplica a ese curso/fecha, si existe.
     * Se usa para explicar por qué no hay clases (jornada sin clases, feriado…).
     */
    public static function excepcionDelDia(Curso $curso, string|Carbon $fecha): ?CalendarioExcepcion
    {
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return CalendarioExcepcion::query()
            ->whereDate('fecha', $fecha->toDateString())
            ->where(fn ($q) => $q->whereNull('curso_id')->orWhere('curso_id', $curso->id))
            // La excepción específica del curso tiene prioridad sobre la general.
            ->orderByRaw('curso_id is null')
            ->first();
    }

    /**
     * Días con clases entre dos fechas (denominador explícito de reportes, §9).
     * Devuelve la lista de fechas (Y-m-d) con clases para el curso+turno.
     */
    public static function diasHabiles(Curso $curso, string|Carbon $desde, string|Carbon $hasta, string $turno): array
    {
        $desde = ($desde instanceof Carbon ? $desde : Carbon::parse($desde))->startOfDay();
        $hasta = ($hasta instanceof Carbon ? $hasta : Carbon::parse($hasta))->startOfDay();

        $dias = [];
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            if (self::hayClases($curso, $d, $turno)) {
                $dias[] = $d->toDateString();
            }
        }

        return $dias;
    }
}
