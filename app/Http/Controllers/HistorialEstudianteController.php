<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Gestion;
use App\Support\Alcance;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historial del alumno (§7): inscripciones, asistencia, salidas, incidencias,
 * citaciones y pagos, mostrando a cada rol únicamente lo autorizado.
 *
 * Confidencialidad (§11): las incidencias confidenciales solo se muestran a
 * Administración; para el resto de roles no aparecen ni siquiera mencionadas
 * (no se filtran por historial, panel ni reportes).
 */
class HistorialEstudianteController extends Controller
{
    public function show(Request $request, Estudiante $estudiante): View
    {
        $user = $request->user();

        // Validación por registro (§6): alcance del usuario sobre este alumno.
        abort_unless(Alcance::puedeVerEstudiante($user, $estudiante), 403);

        $gestion = Gestion::actual();

        $estudiante->load([
            'curso',
            'responsables',
            'inscripciones' => fn ($q) => $q->with(['gestion', 'curso'])->orderByDesc('gestion_id'),
            'asistencias' => fn ($q) => $q->latest('fecha')->limit(200),
            'salidas' => fn ($q) => $q->latest('fecha')->limit(100),
            'incidencias' => fn ($q) => $q->with('categoria')->latest('fecha')->limit(100),
            'citaciones' => fn ($q) => $q->with('padre')->latest('fecha')->limit(100),
        ]);

        $verConfidenciales = $user->esAdministracion();

        // §11: filtrar incidencias confidenciales para todo rol no autorizado.
        $incidencias = $estudiante->incidencias
            ->when(! $verConfidenciales, fn ($col) => $col->reject(fn ($i) => $i->confidencial));

        // Citaciones: el responsable familiar solo ve las dirigidas a él (§5).
        $citaciones = $estudiante->citaciones
            ->when($user->esResponsableFamiliar(), fn ($col) => $col->filter(fn ($c) => (int) $c->padre_id === (int) $user->id));

        // Línea de tiempo combinada, ordenada por fecha descendente.
        $eventos = collect()
            ->merge($estudiante->inscripciones->map(fn ($i) => [
                'fecha' => $i->fecha_inscripcion ?? $i->created_at?->toDateString(),
                'tipo' => 'Inscripción',
                'detalle' => "{$i->gestion?->nombre} — {$i->curso?->etiqueta()} ({$i->estado})",
            ]))
            ->merge($estudiante->asistencias->map(fn ($a) => [
                'fecha' => $a->fecha->toDateString(),
                'tipo' => 'Asistencia',
                'detalle' => $a->nombreTurno().' — '.$a->nombreEstado().($a->observacion ? ' — '.$a->observacion : ''),
            ]))
            ->merge($estudiante->salidas->map(fn ($s) => [
                'fecha' => $s->fecha->toDateString(),
                'tipo' => 'Salida',
                'detalle' => $s->nombreMotivo().' — '.$s->nombreEstado()
                    .($s->responsable_retiro ? " — retira: {$s->responsable_retiro}" : ''),
            ]))
            ->merge($incidencias->map(fn ($i) => [
                'fecha' => $i->fecha->toDateString(),
                'tipo' => 'Incidencia',
                // Para roles no administrativos se muestra solo categoría y estado,
                // sin detalles del caso (§11).
                'detalle' => $verConfidenciales
                    ? "{$i->etiquetaPublica()} ({$i->nombreEstado()})".($i->confidencial ? ' — CONFIDENCIAL' : '')
                    : "{$i->etiquetaPublica()} ({$i->nombreEstado()})",
            ]))
            ->merge($citaciones->map(fn ($c) => [
                'fecha' => $c->fecha->toDateString(),
                'tipo' => 'Citación',
                'detalle' => "{$c->motivo} — {$c->nombreEstado()}",
            ]))
            ->sortByDesc(fn ($e) => $e['fecha'] ?? '')
            ->values();

        return view('historial.show', [
            'estudiante' => $estudiante,
            'eventos' => $eventos,
            'inscripciones' => $estudiante->inscripciones,
            'incidencias' => $incidencias,
            'citaciones' => $citaciones,
            'salidas' => $estudiante->salidas,
            'verConfidenciales' => $verConfidenciales,
            'gestion' => $gestion,
        ]);
    }
}
