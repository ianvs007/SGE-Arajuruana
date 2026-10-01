<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\CalendarioExcepcion;
use App\Models\Curso;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Services\AuditoriaService;
use App\Services\CalendarioAsistencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Asistencia (§9): por alumno, fecha, curso y turno.
 *
 * - Estados: presente, ausente, atrasado, justificada.
 * - "Sin clases programadas" bloquea el registro y NO genera ausentes.
 * - "Sin registro" no equivale a ausente (es la ausencia de fila).
 * - Único por (estudiante, fecha, turno): sin duplicados.
 * - Correcciones autorizadas con trazabilidad (modificado_por + auditoría).
 */
class AsistenciaController extends Controller
{
    public function index(Request $request): View
    {
        $fecha = $request->input('fecha', now()->toDateString());
        $cursoId = $request->input('curso_id');
        $turno = $request->input('turno', 'manana');

        $asistencias = Asistencia::with(['estudiante', 'curso', 'registrador', 'modificador'])
            ->when($fecha, fn ($q) => $q->whereDate('fecha', $fecha))
            ->when($turno, fn ($q) => $q->where('turno', $turno))
            ->when($cursoId, fn ($q) => $q->where('curso_id', $cursoId))
            // 30/09/2026: el responsable familiar verifica SOLO la asistencia de
            // sus representados (validación por registro, §6).
            ->when(
                $request->user()->esResponsableFamiliar(),
                fn ($q) => $q->whereIn('estudiante_id', \App\Support\Alcance::estudiantes($request->user())->pluck('estudiantes.id'))
            )
            ->orderByDesc('fecha')
            ->paginate(30)
            ->withQueryString();

        return view('asistencias.index', [
            'asistencias' => $asistencias,
            'cursos' => $this->cursosVisibles($request),
            'fecha' => $fecha,
            'cursoId' => $cursoId,
            'turno' => $turno,
        ]);
    }

    public function create(Request $request): View
    {
        $fecha = $request->input('fecha', now()->toDateString());
        $cursoId = $request->input('curso_id');
        $turno = $request->input('turno', 'manana');

        $cursos = $this->cursosVisibles($request);
        $curso = $cursoId ? $cursos->firstWhere('id', (int) $cursoId) : null;

        // Alumnos por INSCRIPCIÓN activa del curso (§7): ya no por curso_id suelto.
        $inscripciones = collect();
        $existentes = collect();
        $hayClases = null;
        $excepcion = null;

        if ($curso) {
            $inscripciones = Inscripcion::with('estudiante')
                ->where('curso_id', $curso->id)
                ->where('gestion_id', $curso->gestion_id)
                ->where('estado', 'activa')
                ->orderBy('id')
                ->get()
                ->sortBy(fn ($i) => $i->estudiante?->nombreCompleto())
                ->values();

            $hayClases = CalendarioAsistencia::hayClases($curso, $fecha, $turno);
            $excepcion = CalendarioAsistencia::excepcionDelDia($curso, $fecha);

            $existentes = Asistencia::whereDate('fecha', $fecha)
                ->where('turno', $turno)
                ->whereIn('estudiante_id', $inscripciones->pluck('estudiante_id'))
                ->get()
                ->keyBy('estudiante_id');
        }

        return view('asistencias.create', [
            'cursos' => $cursos,
            'curso' => $curso,
            'inscripciones' => $inscripciones,
            'existentes' => $existentes,
            'fecha' => $fecha,
            'cursoId' => $cursoId,
            'turno' => $turno,
            'hayClases' => $hayClases,
            'excepcion' => $excepcion,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'curso_id' => ['required', 'exists:cursos,id'],
            'turno' => ['required', 'in:manana,tarde'],
            'estados' => ['required', 'array'],
            'estados.*' => ['required', 'in:presente,ausente,atrasado,justificada'],
            'observaciones' => ['nullable', 'array'],
        ]);

        $curso = Curso::findOrFail($data['curso_id']);

        // §5/§6: validación por registro — el docente solo registra sus cursos.
        $user = $request->user();
        if ($user->esDocente() && ! $user->tieneCursoAsignado($curso)) {
            abort(403);
        }

        // §9: sin clases programadas no se registra asistencia (no genera ausentes).
        if (! CalendarioAsistencia::hayClases($curso, $data['fecha'], $data['turno'])) {
            $excepcion = CalendarioAsistencia::excepcionDelDia($curso, $data['fecha']);
            $motivo = $excepcion ? " ({$excepcion->nombreTipo()})" : ' (el curso no tiene clases programadas en ese día/turno)';

            return back()->withInput()
                ->with('error', "No hay clases{$motivo}: no se registra asistencia ni se generan ausentes.");
        }

        // Solo alumnos con inscripción activa en el curso (§7).
        $inscripcionesActivas = Inscripcion::where('curso_id', $curso->id)
            ->where('gestion_id', $curso->gestion_id)
            ->where('estado', 'activa')
            ->get()
            ->keyBy('estudiante_id');

        $registrados = 0;
        $corregidos = 0;

        DB::transaction(function () use ($data, $request, $curso, $inscripcionesActivas, &$registrados, &$corregidos) {
            foreach ($data['estados'] as $estudianteId => $estado) {
                $inscripcion = $inscripcionesActivas[$estudianteId] ?? null;
                if (! $inscripcion) {
                    // Ignora filas que no pertenecen al curso (defensa por registro, §6).
                    continue;
                }

                $existente = Asistencia::where('estudiante_id', $estudianteId)
                    ->whereDate('fecha', $data['fecha'])
                    ->where('turno', $data['turno'])
                    ->first();

                if ($existente) {
                    // Corrección autorizada con trazabilidad (§9).
                    $cambios = collect(['estado', 'curso_id', 'observacion'])
                        ->mapWithKeys(fn ($campo) => [$campo => $campo === 'observacion'
                            ? ($data['observaciones'][$estudianteId] ?? null)
                            : ($campo === 'curso_id' ? $curso->id : $estado)])
                        ->filter(fn ($valor, $campo) => $existente->{$campo} !== $valor)
                        ->isNotEmpty();

                    if ($cambios) {
                        $existente->update([
                            'estado' => $estado,
                            'curso_id' => $curso->id,
                            'observacion' => $data['observaciones'][$estudianteId] ?? null,
                            'modificado_por' => $request->user()->id,
                        ]);
                        $corregidos++;
                    }

                    continue;
                }

                Asistencia::create([
                    'estudiante_id' => $estudianteId,
                    'inscripcion_id' => $inscripcion->id,
                    'curso_id' => $curso->id,
                    'fecha' => $data['fecha'],
                    'turno' => $data['turno'],
                    'estado' => $estado,
                    'observacion' => $data['observaciones'][$estudianteId] ?? null,
                    'registrado_por' => $request->user()->id,
                ]);
                $registrados++;
            }
        });

        if ($corregidos > 0) {
            AuditoriaService::registrar('asistencia.corregir', $curso, [
                'fecha' => $data['fecha'],
                'turno' => $data['turno'],
                'corregidos' => $corregidos,
            ]);
        }

        $mensaje = "Asistencia registrada: {$registrados} nuevo(s)";
        if ($corregidos > 0) {
            $mensaje .= ", {$corregidos} corrección(es) con trazabilidad";
        }

        return redirect()
            ->route('asistencias.index', ['fecha' => $data['fecha'], 'curso_id' => $data['curso_id'], 'turno' => $data['turno']])
            ->with('success', $mensaje.'.');
    }

    /**
     * Reporte de asistencia con denominador explícito (§9): separa ausencias,
     * falta de registro y jornadas no aplicables. Los totales coinciden con
     * pantalla/PDF/Excel (§16, Etapa 5 conectará las exportaciones).
     */
    public function reporte(Request $request): View
    {
        // 30/09/2026: el reporte por curso agrega datos de TODA la clase; el
        // responsable familiar verifica la asistencia de SUS hijos en el
        // listado diario y en el historial, no en el reporte institucional (§6).
        abort_if($request->user()->esResponsableFamiliar(), 403);

        $data = $request->validate([
            'curso_id' => ['required', 'exists:cursos,id'],
            'turno' => ['required', 'in:manana,tarde'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $curso = Curso::with('gestion')->findOrFail($data['curso_id']);

        // §6: validación por registro — docente solo sus cursos.
        $user = $request->user();
        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            abort_unless($user->tieneCursoAsignado($curso), 403);
        }

        return view('asistencias.reporte', [
            'curso' => $curso,
            'turno' => $data['turno'],
            'desde' => $data['desde'],
            'hasta' => $data['hasta'],
            'filas' => \App\Services\EstadisticaAsistencia::porCurso($curso, $data['turno'], $data['desde'], $data['hasta']),
            'totales' => \App\Services\EstadisticaAsistencia::totalesCurso($curso, $data['turno'], $data['desde'], $data['hasta']),
            'cursos' => $this->cursosVisibles($request),
        ]);
    }

    /**
     * Cursos visibles: docente → solo los suyos (asignados en la gestión actual);
     * institucional → todos los activos. Responsable familiar no llega aquí
     * (la ruta exige asistencia.gestionar).
     */
    private function cursosVisibles(Request $request)
    {
        $user = $request->user();
        $gestion = Gestion::actual();

        $query = Curso::where('activo', true)
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->orderBy('orden')
            ->orderBy('nombre');

        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            $query->whereIn('cursos.id', \App\Support\Alcance::cursoIdsDocente($user));
        }

        return $query->get();
    }
}
