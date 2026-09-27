<?php

namespace App\Http\Controllers;

use App\Exports\AportePorAlumnoExport;
use App\Exports\AportePorCursoExport;
use App\Exports\AsistenciaCursoExport;
use App\Models\Asistencia;
use App\Models\CargoCuenta;
use App\Models\Citacion;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Incidencia;
use App\Models\Pago;
use App\Models\SalidaEstudiante;
use App\Services\ReporteService;
use App\Support\Alcance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reportes (§16).
 *
 * Regla confirmada: los totales de PDF y Excel son IDÉNTICOS a los de pantalla
 * porque las tres salidas consumen `ReporteService` (que reutiliza
 * `EstadisticaAsistencia` y `AporteService::estadoDeCuenta()` + `Dinero`).
 *
 * Alcance (§6): el docente solo reporta sus cursos asignados; el módulo exige
 * `reportes.ver`. Los reportes económicos exigen además `aporte.cuotas.ver`.
 */
class ReporteController extends Controller
{
    public function index(Request $request): View
    {
        return view('reportes.index', [
            'cursos' => $this->cursosVisibles($request),
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'gestionActual' => Gestion::actual(),
            'verEconomico' => $request->user()->can('aporte.cuotas.ver'),
        ]);
    }

    public function estudiantes(): View
    {
        $estudiantes = Estudiante::with('curso')->orderBy('apellidos')->get();

        return view('reportes.estudiantes', compact('estudiantes'));
    }

    public function asistencia(Request $request): View
    {
        $fecha = $request->input('fecha', now()->toDateString());
        $asistencias = Asistencia::with(['estudiante.curso'])->whereDate('fecha', $fecha)->get();

        return view('reportes.asistencia', compact('asistencias', 'fecha'));
    }

    public function salidas(): View
    {
        $salidas = SalidaEstudiante::with('estudiante')->latest('fecha')->get();

        return view('reportes.salidas', compact('salidas'));
    }

    public function incidencias(Request $request): View
    {
        // §11/§20.8: las confidenciales solo las ve Administración
        // (permiso incidencias.confidenciales); ningún otro rol las recibe.
        $incidencias = Incidencia::with(['estudiante', 'categoria'])
            ->when(! $request->user()->can('incidencias.confidenciales'),
                fn ($q) => $q->where('confidencial', false))
            ->latest('fecha')
            ->get();

        return view('reportes.incidencias', compact('incidencias'));
    }

    public function citaciones(): View
    {
        $citaciones = Citacion::with(['estudiante', 'padre'])->latest('fecha')->get();

        return view('reportes.citaciones', compact('citaciones'));
    }

    public function cuentas(): View
    {
        $cargos = CargoCuenta::with(['padre', 'estudiante'])->whereIn('estado', ['pendiente', 'parcial'])->get();

        return view('reportes.cuentas', compact('cargos'));
    }

    public function pagos(): View
    {
        $pagos = Pago::with(['padre', 'cargo'])->where('estado', 'confirmado')->latest()->get();

        return view('reportes.pagos', compact('pagos'));
    }

    // =====================================================================
    // Reportes con PDF/Excel y totales idénticos a pantalla (§16, Etapa 5)
    // =====================================================================

    /** Asistencia por curso/turno/rango: pantalla con los mismos datos del PDF/Excel. */
    public function asistenciaCurso(Request $request): View
    {
        $cursos = $this->cursosVisibles($request);

        // Sin parámetros: valores por defecto razonables (primer curso visible,
        // mes en curso) para que la pantalla abra desde el índice de reportes.
        if (! $request->filled('curso_id') && $cursos->isEmpty()) {
            return view('reportes.asistencia_curso', [
                'data' => null, 'cursos' => $cursos, 'filtro' => [],
            ]);
        }

        if (! $request->filled('curso_id')) {
            $curso = $cursos->first();
            $request->merge([
                'curso_id' => $curso->id,
                'turno' => $request->input('turno', in_array($curso->turno, ['manana', 'tarde'], true) ? $curso->turno : 'manana'),
                'desde' => $request->input('desde', now()->startOfMonth()->toDateString()),
                'hasta' => $request->input('hasta', now()->toDateString()),
            ]);
        }

        $data = $this->validarAsistencia($request);

        return view('reportes.asistencia_curso', [
            'data' => ReporteService::asistenciaCurso($data['curso'], $data['turno'], $data['desde'], $data['hasta']),
            'cursos' => $cursos,
            'filtro' => $data['filtro'],
        ]);
    }

    public function asistenciaCursoPdf(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $data = $this->validarAsistencia($request);
        $reporte = ReporteService::asistenciaCurso($data['curso'], $data['turno'], $data['desde'], $data['hasta']);

        $pdf = Pdf::loadView('reportes.pdf.asistencia_curso', ['data' => $reporte])->setPaper('a4');

        return $pdf->stream('asistencia-'.$data['curso']->id.'-'.$data['filtro']['desde'].'_'.$data['filtro']['hasta'].'.pdf');
    }

    public function asistenciaCursoExcel(Request $request): BinaryFileResponse
    {
        $data = $this->validarAsistencia($request);
        $reporte = ReporteService::asistenciaCurso($data['curso'], $data['turno'], $data['desde'], $data['hasta']);

        return Excel::download(
            new AsistenciaCursoExport($reporte),
            'asistencia-curso-'.$data['filtro']['desde'].'_'.$data['filtro']['hasta'].'.xlsx'
        );
    }

    /** Económico por curso: pantalla con totales (misma fuente que PDF/Excel). */
    public function aporteCurso(Request $request): View
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $gestion = $this->gestionFiltrada($request);

        return view('reportes.aporte_curso', [
            'data' => ReporteService::aportePorCurso($gestion),
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'gestion' => $gestion,
        ]);
    }

    public function aporteCursoPdf(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $data = ReporteService::aportePorCurso($this->gestionFiltrada($request));

        $pdf = Pdf::loadView('reportes.pdf.aporte_por_curso', ['data' => $data])->setPaper('a4');

        return $pdf->stream('aporte-por-curso-'.now()->format('Y-m-d').'.pdf');
    }

    public function aporteCursoExcel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $data = ReporteService::aportePorCurso($this->gestionFiltrada($request));

        return Excel::download(new AportePorCursoExport($data), 'aporte-por-curso-'.now()->format('Y-m-d').'.xlsx');
    }

    /** Económico por alumno: pantalla con totales (misma fuente que PDF/Excel). */
    public function aporteAlumno(Request $request): View
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $gestion = $this->gestionFiltrada($request);
        [$filas, $totales] = $this->aporteAlumnoData($request, $gestion);

        return view('reportes.aporte_alumno', [
            'filas' => $filas,
            'totales' => $totales,
            'hoy' => now()->toDateString(),
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'gestion' => $gestion,
            'cursos' => $this->cursosVisibles($request),
        ]);
    }

    public function aporteAlumnoPdf(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $gestion = $this->gestionFiltrada($request);
        [$filas, $totales] = $this->aporteAlumnoData($request, $gestion);

        $pdf = Pdf::loadView('reportes.pdf.aporte_por_alumno', [
            'data' => ['gestion' => $gestion, 'filas' => $filas, 'totales' => $totales, 'hoy' => now()->toDateString()],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('aporte-por-alumno-'.now()->format('Y-m-d').'.pdf');
    }

    public function aporteAlumnoExcel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $gestion = $this->gestionFiltrada($request);
        [$filas, $totales] = $this->aporteAlumnoData($request, $gestion);

        return Excel::download(
            new AportePorAlumnoExport([
                'gestion' => $gestion, 'filas' => $filas, 'totales' => $totales, 'hoy' => now()->toDateString(),
            ]),
            'aporte-por-alumno-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    // ------------------------------------------------------------------

    /** Validación + autorización por registro del reporte de asistencia (§6). */
    private function validarAsistencia(Request $request): array
    {
        $filtro = $request->validate([
            'curso_id' => ['required', 'exists:cursos,id'],
            'turno' => ['required', 'in:manana,tarde'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $curso = Curso::with('gestion')->findOrFail($filtro['curso_id']);

        // §6: docente solo sus cursos asignados.
        $user = $request->user();
        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            abort_unless($user->tieneCursoAsignado($curso), 403);
        }

        return [
            'curso' => $curso,
            'turno' => $filtro['turno'],
            'desde' => $filtro['desde'],
            'hasta' => $filtro['hasta'],
            'filtro' => $filtro,
        ];
    }

    /**
     * Datos del reporte por alumno, con filtro opcional de curso. Reutiliza
     * `ReporteService::aportePorAlumno()` (misma fuente para pantalla/PDF/Excel).
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function aporteAlumnoData(Request $request, ?Gestion $gestion): array
    {
        $cursoId = $request->query('curso_id');
        $estudiantes = null;

        if ($cursoId) {
            $curso = Curso::findOrFail($cursoId);
            // §6: docente solo sus cursos.
            $user = $request->user();
            if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
                abort_unless($user->tieneCursoAsignado($curso), 403);
            }

            // Alumnos del curso por inscripción activa de la gestión (§14).
            $estudiantes = $curso->estudiantesInscritos()
                ->orderBy('apellidos')->orderBy('nombres')
                ->get();
        }

        $reporte = ReporteService::aportePorAlumno($gestion, $estudiantes);

        return [$reporte['filas'], $reporte['totales']];
    }

    /** Gestión del filtro (query `gestion`) o la actual por defecto. */
    private function gestionFiltrada(Request $request): ?Gestion
    {
        if ($id = $request->query('gestion')) {
            return Gestion::find($id);
        }

        return Gestion::actual();
    }

    /** Cursos visibles según alcance (§6): docente → solo los asignados. */
    private function cursosVisibles(Request $request)
    {
        $user = $request->user();
        $gestion = Gestion::actual();

        $query = Curso::where('activo', true)
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->orderBy('orden')
            ->orderBy('nombre');

        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            $query->whereIn('cursos.id', Alcance::cursoIdsDocente($user));
        }

        return $query->get();
    }
}
