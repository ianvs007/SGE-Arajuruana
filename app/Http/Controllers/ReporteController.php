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
 * Controlador del módulo de reportes.
 *
 * Genera los reportes del sistema: listados simples (estudiantes, asistencia
 * diaria, salidas, incidencias, citaciones, cuentas y pagos) y reportes
 * oficiales que se pueden ver en pantalla y también descargar en PDF y Excel
 * (asistencia por curso y aporte económico por curso o por alumno).
 *
 * Una regla importante es que los totales del PDF y del Excel tienen que ser
 * idénticos a los de la pantalla. Para garantizarlo, las tres salidas obtienen
 * sus datos del mismo lugar: ReporteService, que a su vez reutiliza
 * EstadisticaAsistencia, AporteService::estadoDeCuenta() y la clase Dinero.
 *
 * En cuanto a permisos, el módulo exige "reportes.ver" (el reporte de
 * asistencia por curso también acepta "asistencia.ver"). El docente solo
 * puede reportar sus cursos asignados y los reportes económicos exigen
 * además el permiso "aporte.cuotas.ver".
 */
class ReporteController extends Controller
{
    /**
     * Muestra la pantalla principal de reportes.
     *
     * Se envían los cursos que el usuario puede ver, las gestiones para los
     * filtros y un indicador para mostrar u ocultar la sección económica.
     *
     * @return View Vista índice de reportes.
     */
    public function index(Request $request): View
    {
        return view('reportes.index', [
            'cursos' => $this->cursosVisibles($request),
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'gestionActual' => Gestion::actual(),
            'verEconomico' => $request->user()->can('aporte.cuotas.ver'),
        ]);
    }

    /**
     * Reporte con el listado de todos los estudiantes y su curso.
     *
     * @return View Vista del reporte de estudiantes ordenado por apellidos.
     */
    public function estudiantes(): View
    {
        $estudiantes = Estudiante::with('curso')->orderBy('apellidos')->get();

        return view('reportes.estudiantes', compact('estudiantes'));
    }

    /**
     * Reporte de la asistencia registrada en un día.
     *
     * Si no se indica una fecha, se muestra la asistencia del día de hoy.
     *
     * @return View Vista del reporte de asistencia diaria.
     */
    public function asistencia(Request $request): View
    {
        $fecha = $request->input('fecha', now()->toDateString());
        $asistencias = Asistencia::with(['estudiante.curso'])->whereDate('fecha', $fecha)->get();

        return view('reportes.asistencia', compact('asistencias', 'fecha'));
    }

    /**
     * Reporte de las salidas de estudiantes, de la más reciente a la más antigua.
     *
     * @return View Vista del reporte de salidas.
     */
    public function salidas(): View
    {
        $salidas = SalidaEstudiante::with('estudiante')->latest('fecha')->get();

        return view('reportes.salidas', compact('salidas'));
    }

    /**
     * Reporte de incidencias.
     *
     * Las incidencias confidenciales solo se incluyen si el usuario tiene el
     * permiso "incidencias.confidenciales", que es exclusivo de
     * Administración. A cualquier otro rol nunca le llegan esos casos.
     *
     * @return View Vista del reporte de incidencias.
     */
    public function incidencias(Request $request): View
    {
        // Si el usuario no puede ver confidenciales, las excluimos directamente en la consulta.
        $incidencias = Incidencia::with(['estudiante', 'categoria'])
            ->when(! $request->user()->can('incidencias.confidenciales'),
                fn ($q) => $q->where('confidencial', false))
            ->latest('fecha')
            ->get();

        return view('reportes.incidencias', compact('incidencias'));
    }

    /**
     * Reporte de citaciones a padres de familia.
     *
     * @return View Vista del reporte de citaciones.
     */
    public function citaciones(): View
    {
        $citaciones = Citacion::with(['estudiante', 'padre'])->latest('fecha')->get();

        return view('reportes.citaciones', compact('citaciones'));
    }

    /**
     * Reporte de cuentas por cobrar.
     *
     * Solo se incluyen los cargos que todavía tienen saldo, es decir, los
     * que están en estado "pendiente" o "parcial".
     *
     * @return View Vista del reporte de cuentas pendientes.
     */
    public function cuentas(): View
    {
        $cargos = CargoCuenta::with(['padre', 'estudiante'])->whereIn('estado', ['pendiente', 'parcial'])->get();

        return view('reportes.cuentas', compact('cargos'));
    }

    /**
     * Reporte de pagos confirmados.
     *
     * Solo se incluyen los pagos ya confirmados por un operador, porque son
     * los únicos que realmente cuentan como dinero recibido.
     *
     * @return View Vista del reporte de pagos.
     */
    public function pagos(): View
    {
        $pagos = Pago::with(['padre', 'cargo'])->where('estado', 'confirmado')->latest()->get();

        return view('reportes.pagos', compact('pagos'));
    }

    // =====================================================================
    // Reportes con PDF/Excel y totales idénticos a los de pantalla
    // =====================================================================

    /**
     * Reporte de asistencia por curso, turno y rango de fechas (en pantalla).
     *
     * Usa exactamente los mismos datos que el PDF y el Excel. Si se entra sin
     * filtros, se toman valores por defecto razonables (el primer curso
     * visible y el mes en curso) para que la pantalla se pueda abrir
     * directamente desde el índice de reportes.
     *
     * @return View Vista del reporte de asistencia por curso.
     */
    public function asistenciaCurso(Request $request): View
    {
        $cursos = $this->cursosVisibles($request);

        // Si no se eligió curso y el usuario no tiene ningún curso visible, mostramos
        // la pantalla vacía en lugar de un error.
        if (! $request->filled('curso_id') && $cursos->isEmpty()) {
            return view('reportes.asistencia_curso', [
                'data' => null, 'cursos' => $cursos, 'filtro' => [],
            ]);
        }

        // Si no se eligió curso, completamos los filtros con valores por defecto: el primer
        // curso visible, su turno (o "mañana" si no está definido) y desde el inicio del mes hasta hoy.
        if (! $request->filled('curso_id')) {
            $curso = $cursos->first();
            $request->merge([
                'curso_id' => $curso->id,
                'turno' => $request->input('turno', in_array($curso->turno, ['manana', 'tarde'], true) ? $curso->turno : 'manana'),
                'desde' => $request->input('desde', now()->startOfMonth()->toDateString()),
                'hasta' => $request->input('hasta', now()->toDateString()),
            ]);
        }

        // Validamos los filtros y comprobamos que el usuario pueda reportar ese curso.
        $data = $this->validarAsistencia($request);

        return view('reportes.asistencia_curso', [
            'data' => ReporteService::asistenciaCurso($data['curso'], $data['turno'], $data['desde'], $data['hasta']),
            'cursos' => $cursos,
            'filtro' => $data['filtro'],
        ]);
    }

    /**
     * Genera el PDF del reporte de asistencia por curso.
     *
     * El nombre del archivo incluye el curso y el rango de fechas para que
     * sea fácil identificarlo después de descargarlo.
     *
     * @return BinaryFileResponse|\Illuminate\Http\Response PDF que se abre en el navegador.
     */
    public function asistenciaCursoPdf(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $data = $this->validarAsistencia($request);
        $reporte = ReporteService::asistenciaCurso($data['curso'], $data['turno'], $data['desde'], $data['hasta']);

        $pdf = Pdf::loadView('reportes.pdf.asistencia_curso', ['data' => $reporte])->setPaper('a4');

        return $pdf->stream('asistencia-'.$data['curso']->id.'-'.$data['filtro']['desde'].'_'.$data['filtro']['hasta'].'.pdf');
    }

    /**
     * Genera el Excel del reporte de asistencia por curso.
     *
     * @return BinaryFileResponse Archivo .xlsx para descargar.
     */
    public function asistenciaCursoExcel(Request $request): BinaryFileResponse
    {
        $data = $this->validarAsistencia($request);
        $reporte = ReporteService::asistenciaCurso($data['curso'], $data['turno'], $data['desde'], $data['hasta']);

        return Excel::download(
            new AsistenciaCursoExport($reporte),
            'asistencia-curso-'.$data['filtro']['desde'].'_'.$data['filtro']['hasta'].'.xlsx'
        );
    }

    /**
     * Reporte económico del aporte agrupado por curso (en pantalla).
     *
     * Muestra los totales por curso de la gestión elegida, usando la misma
     * fuente de datos que el PDF y el Excel. Requiere el permiso "aporte.cuotas.ver".
     *
     * @return View Vista del reporte de aporte por curso.
     */
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

    /**
     * Genera el PDF del reporte de aporte por curso.
     *
     * @return BinaryFileResponse|\Illuminate\Http\Response PDF que se abre en el navegador.
     */
    public function aporteCursoPdf(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $data = ReporteService::aportePorCurso($this->gestionFiltrada($request));

        $pdf = Pdf::loadView('reportes.pdf.aporte_por_curso', ['data' => $data])->setPaper('a4');

        return $pdf->stream('aporte-por-curso-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Genera el Excel del reporte de aporte por curso.
     *
     * @return BinaryFileResponse Archivo .xlsx para descargar.
     */
    public function aporteCursoExcel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);
        $data = ReporteService::aportePorCurso($this->gestionFiltrada($request));

        return Excel::download(new AportePorCursoExport($data), 'aporte-por-curso-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Reporte económico del aporte detallado por alumno (en pantalla).
     *
     * Se puede filtrar por gestión y por curso. Los totales se calculan con
     * la misma fuente que el PDF y el Excel. Requiere "aporte.cuotas.ver".
     *
     * @return View Vista del reporte de aporte por alumno.
     */
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

    /**
     * Genera el PDF del reporte de aporte por alumno.
     *
     * Se usa la hoja en orientación horizontal porque el reporte tiene
     * muchas columnas y en vertical no entrarían cómodamente.
     *
     * @return BinaryFileResponse|\Illuminate\Http\Response PDF que se abre en el navegador.
     */
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

    /**
     * Genera el Excel del reporte de aporte por alumno.
     *
     * @return BinaryFileResponse Archivo .xlsx para descargar.
     */
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

    /**
     * Valida los filtros del reporte de asistencia y autoriza el acceso al curso.
     *
     * La comparten la versión en pantalla, en PDF y en Excel, para que las
     * tres apliquen exactamente las mismas reglas.
     *
     * @return array Curso, turno, rango de fechas y los filtros validados.
     */
    private function validarAsistencia(Request $request): array
    {
        // El reporte por curso muestra datos de toda la clase, así que el responsable
        // familiar no puede usarlo, aunque tenga "asistencia.ver". Él revisa la asistencia
        // de sus hijos en el listado diario y en el historial (decisión del 30/09/2026).
        abort_if($request->user()->esResponsableFamiliar(), 403);

        // Validamos el curso, el turno y que la fecha final no sea anterior a la inicial.
        $filtro = $request->validate([
            'curso_id' => ['required', 'exists:cursos,id'],
            'turno' => ['required', 'in:manana,tarde'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $curso = Curso::with('gestion')->findOrFail($filtro['curso_id']);

        // El docente solo puede reportar los cursos que tiene asignados.
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
     * Obtiene los datos del reporte de aporte por alumno.
     *
     * Si se indicó un curso, se limitan los alumnos a los inscritos en él;
     * si no, el servicio trabaja con todos los alumnos de la gestión. Se
     * reutiliza ReporteService::aportePorAlumno(), que es la misma fuente
     * para la pantalla, el PDF y el Excel.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array} Filas del reporte y sus totales.
     */
    private function aporteAlumnoData(Request $request, ?Gestion $gestion): array
    {
        $cursoId = $request->query('curso_id');
        $estudiantes = null;

        if ($cursoId) {
            $curso = Curso::findOrFail($cursoId);
            // El docente solo puede consultar los cursos que tiene asignados.
            $user = $request->user();
            if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
                abort_unless($user->tieneCursoAsignado($curso), 403);
            }

            // Tomamos los alumnos del curso según su inscripción activa en la gestión,
            // ya que la obligación del aporte depende de la inscripción.
            $estudiantes = $curso->estudiantesInscritos()
                ->orderBy('apellidos')->orderBy('nombres')
                ->get();
        }

        $reporte = ReporteService::aportePorAlumno($gestion, $estudiantes);

        return [$reporte['filas'], $reporte['totales']];
    }

    /**
     * Determina la gestión que se usará en los reportes económicos.
     *
     * Si en la dirección viene el parámetro "gestion" se usa esa; si no, se
     * toma la gestión actual.
     *
     * @return Gestion|null Gestión elegida o null si no existe.
     */
    private function gestionFiltrada(Request $request): ?Gestion
    {
        if ($id = $request->query('gestion')) {
            return Gestion::find($id);
        }

        return Gestion::actual();
    }

    /**
     * Obtiene los cursos que el usuario puede ver en los reportes.
     *
     * Se listan los cursos activos de la gestión actual. Si el usuario es
     * docente sin alcance institucional, solo se incluyen sus cursos asignados.
     *
     * @return \Illuminate\Support\Collection Cursos visibles, ordenados.
     */
    private function cursosVisibles(Request $request)
    {
        $user = $request->user();
        $gestion = Gestion::actual();

        // Cursos activos de la gestión actual (si existe), en el orden configurado.
        $query = Curso::where('activo', true)
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->orderBy('orden')
            ->orderBy('nombre');

        // Al docente le limitamos la lista a los cursos que tiene asignados.
        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            $query->whereIn('cursos.id', Alcance::cursoIdsDocente($user));
        }

        return $query->get();
    }
}
