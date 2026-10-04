<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Gestion;
use App\Support\Alcance;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador del historial del estudiante.
 *
 * Reúne en una sola pantalla todo lo que ocurrió con un alumno: sus
 * inscripciones, asistencias, salidas, incidencias y citaciones, ordenado
 * como una línea de tiempo. Lo pueden usar los roles con permiso
 * "historial.ver" o "estudiantes.ver" (personal institucional, docentes y
 * responsables familiares), pero cada uno ve únicamente lo que le corresponde.
 *
 * Un punto importante es la confidencialidad: las incidencias marcadas como
 * confidenciales solo las ve Administración. Para el resto de los roles ni
 * siquiera aparecen mencionadas, ni en el historial, ni en el panel, ni en
 * los reportes.
 */
class HistorialEstudianteController extends Controller
{
    /**
     * Muestra el historial completo de un estudiante.
     *
     * Primero se verifica que el usuario tenga permiso sobre ese alumno en
     * particular. Luego se cargan sus registros relacionados, se filtran los
     * que el rol no debe ver y se arma una línea de tiempo combinada.
     *
     * @param  Estudiante  $estudiante  Alumno cuyo historial se quiere consultar.
     * @return View Vista del historial con la línea de tiempo y los detalles.
     */
    public function show(Request $request, Estudiante $estudiante): View
    {
        $user = $request->user();

        // No basta con tener el permiso general: también comprobamos que este alumno esté
        // dentro del alcance del usuario (por ejemplo, que un padre no pueda ver a un
        // estudiante que no es su hijo cambiando el número en la dirección web).
        abort_unless(Alcance::puedeVerEstudiante($user, $estudiante), 403);

        $gestion = Gestion::actual();

        // Cargamos de una sola vez todas las relaciones que necesitamos, así evitamos hacer
        // muchas consultas pequeñas. Ponemos límites a los registros más numerosos para
        // que la página no se vuelva demasiado pesada.
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

        // Si el usuario no es de Administración, quitamos de la lista las incidencias
        // confidenciales para que no aparezcan en ninguna parte de la pantalla.
        $incidencias = $estudiante->incidencias
            ->when(! $verConfidenciales, fn ($col) => $col->reject(fn ($i) => $i->confidencial));

        // Un responsable familiar solo debe ver las citaciones dirigidas a él, no las que
        // se enviaron a otro familiar del mismo estudiante.
        $citaciones = $estudiante->citaciones
            ->when($user->esResponsableFamiliar(), fn ($col) => $col->filter(fn ($c) => (int) $c->padre_id === (int) $user->id));

        // Armamos una línea de tiempo uniendo todos los tipos de eventos en un mismo
        // formato (fecha, tipo y detalle) y la ordenamos de lo más reciente a lo más antiguo.
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
                // A los roles que no son de Administración solo les mostramos la categoría
                // y el estado, sin los detalles del caso, para proteger la privacidad del alumno.
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

        // Enviamos a la vista la línea de tiempo y también las listas ya filtradas por rol.
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
