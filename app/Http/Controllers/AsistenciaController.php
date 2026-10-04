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
 * Controlador del módulo de asistencia diaria de los estudiantes.
 *
 * La asistencia se registra por alumno, fecha, curso y turno (mañana o tarde).
 * Desde aquí se consulta el listado diario, se registra la asistencia de un
 * curso completo y se genera el reporte de asistencia por rango de fechas.
 * Las reglas que seguimos son las siguientes:
 *
 * - Los estados posibles son: presente, ausente, atrasado y justificada.
 * - Si el curso no tiene clases programadas ese día (feriado, suspensión o
 *   simplemente no le toca ese turno), no se permite registrar asistencia y
 *   tampoco se generan ausentes, para no perjudicar al alumno.
 * - "Sin registro" no es lo mismo que "ausente": simplemente significa que no
 *   existe una fila de asistencia para ese alumno en esa fecha y turno.
 * - Solo puede existir un registro por estudiante, fecha y turno, así se
 *   evitan los duplicados.
 * - Las correcciones están permitidas, pero quedan trazadas: se guarda quién
 *   modificó el registro (`modificado_por`) y se deja constancia en la auditoría.
 *
 * Roles: Administración, Director, Coordinadora y Docente registran la
 * asistencia (permiso `asistencia.gestionar`); el Docente solo en los cursos
 * que tiene asignados. El responsable familiar, con `asistencia.ver`, solo
 * consulta en modo lectura la asistencia de sus representados.
 */
class AsistenciaController extends Controller
{
    /**
     * Muestra el listado de asistencias filtrado por fecha, turno y curso.
     *
     * Por defecto se muestra la fecha de hoy y el turno de la mañana. Si quien
     * consulta es un responsable familiar, el listado se limita a la asistencia
     * de sus propios representados.
     *
     * @return View Vista `asistencias.index` con los registros y los filtros aplicados.
     */
    public function index(Request $request): View
    {
        // Leemos los filtros de la URL; si no vienen, usamos valores por defecto
        // razonables para que la pantalla muestre algo útil desde el inicio.
        $fecha = $request->input('fecha', now()->toDateString());
        $cursoId = $request->input('curso_id');
        $turno = $request->input('turno', 'manana');

        // Cargamos junto con cada asistencia al estudiante, el curso y los
        // usuarios que la registraron o modificaron, y aplicamos solo los
        // filtros que realmente se enviaron (por eso usamos when()).
        $asistencias = Asistencia::with(['estudiante', 'curso', 'registrador', 'modificador'])
            ->when($fecha, fn ($q) => $q->whereDate('fecha', $fecha))
            ->when($turno, fn ($q) => $q->where('turno', $turno))
            ->when($cursoId, fn ($q) => $q->where('curso_id', $cursoId))
            // Desde la decisión del colegio del 30/09/2026, el responsable
            // familiar puede revisar la asistencia, pero SOLO la de sus
            // representados. Es una validación por registro: aunque tenga
            // acceso a la ruta, no ve filas de otros alumnos.
            ->when(
                $request->user()->esResponsableFamiliar(),
                fn ($q) => $q->whereIn('estudiante_id', \App\Support\Alcance::estudiantes($request->user())->pluck('estudiantes.id'))
            )
            // Mostramos primero lo más reciente y conservamos los filtros en la paginación.
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

    /**
     * Muestra el formulario para registrar la asistencia de un curso.
     *
     * El usuario elige fecha, curso y turno. Con esos datos se carga la lista
     * de alumnos inscritos, se verifica si ese día hay clases según el
     * calendario y se recuperan las asistencias que ya estaban registradas,
     * para que el formulario aparezca precargado y sirva también para corregir.
     *
     * @return View Vista `asistencias.create`.
     */
    public function create(Request $request): View
    {
        $fecha = $request->input('fecha', now()->toDateString());
        $cursoId = $request->input('curso_id');
        $turno = $request->input('turno', 'manana');

        // El curso elegido se busca dentro de los cursos que el usuario puede
        // ver. Así un docente no puede abrir el formulario de un curso ajeno
        // simplemente cambiando el id en la URL: en ese caso $curso queda en null.
        $cursos = $this->cursosVisibles($request);
        $curso = $cursoId ? $cursos->firstWhere('id', (int) $cursoId) : null;

        // Los alumnos se obtienen a partir de su inscripción activa en el curso
        // y no por el campo curso_id del estudiante, porque la inscripción es la
        // que refleja en qué curso está realmente el alumno en esta gestión.
        // Inicializamos las variables vacías por si todavía no se eligió curso.
        $inscripciones = collect();
        $existentes = collect();
        $hayClases = null;
        $excepcion = null;

        if ($curso) {
            // Inscripciones activas del curso en su gestión, ordenadas
            // alfabéticamente por el nombre completo del alumno para que la
            // lista coincida con la que maneja el docente en el aula.
            $inscripciones = Inscripcion::with('estudiante')
                ->where('curso_id', $curso->id)
                ->where('gestion_id', $curso->gestion_id)
                ->where('estado', 'activa')
                ->orderBy('id')
                ->get()
                ->sortBy(fn ($i) => $i->estudiante?->nombreCompleto())
                ->values();

            // Consultamos el calendario para saber si ese día y turno hay
            // clases, y si existe alguna excepción (feriado, suspensión, etc.)
            // para poder explicarle al usuario por qué no se puede registrar.
            $hayClases = CalendarioAsistencia::hayClases($curso, $fecha, $turno);
            $excepcion = CalendarioAsistencia::excepcionDelDia($curso, $fecha);

            // Asistencias ya guardadas para esos alumnos en esa fecha y turno,
            // indexadas por estudiante para precargar rápidamente el formulario.
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

    /**
     * Guarda la asistencia de un curso para una fecha y un turno.
     *
     * Antes de guardar se verifica que el docente tenga asignado el curso y que
     * ese día haya clases. Luego, dentro de una transacción, se recorre cada
     * alumno: si ya tenía asistencia se corrige (solo si algo cambió) y si no,
     * se crea un registro nuevo. Las correcciones quedan en la auditoría.
     *
     * @return RedirectResponse Redirige al listado con un resumen de lo registrado.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validamos los datos generales y el arreglo de estados, que viene
        // indexado por id de estudiante (estados[id] => estado).
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'curso_id' => ['required', 'exists:cursos,id'],
            'turno' => ['required', 'in:manana,tarde'],
            'estados' => ['required', 'array'],
            'estados.*' => ['required', 'in:presente,ausente,atrasado,justificada'],
            'observaciones' => ['nullable', 'array'],
        ]);

        $curso = Curso::findOrFail($data['curso_id']);

        // Validación por registro: el docente solo puede registrar asistencia en
        // los cursos que tiene asignados, aunque tenga el permiso general.
        $user = $request->user();
        if ($user->esDocente() && ! $user->tieneCursoAsignado($curso)) {
            abort(403);
        }

        // Si ese día no hay clases programadas no se registra asistencia, para
        // no generar ausentes injustos. Armamos un mensaje que explique el
        // motivo: la excepción del calendario o que el curso no tiene clases
        // en ese día o turno.
        if (! CalendarioAsistencia::hayClases($curso, $data['fecha'], $data['turno'])) {
            $excepcion = CalendarioAsistencia::excepcionDelDia($curso, $data['fecha']);
            $motivo = $excepcion ? " ({$excepcion->nombreTipo()})" : ' (el curso no tiene clases programadas en ese día/turno)';

            return back()->withInput()
                ->with('error', "No hay clases{$motivo}: no se registra asistencia ni se generan ausentes.");
        }

        // Solo se acepta asistencia de alumnos con inscripción activa en el
        // curso; las indexamos por estudiante para buscarlas rápido en el bucle.
        $inscripcionesActivas = Inscripcion::where('curso_id', $curso->id)
            ->where('gestion_id', $curso->gestion_id)
            ->where('estado', 'activa')
            ->get()
            ->keyBy('estudiante_id');

        // Contadores para el mensaje final. Se pasan por referencia a la
        // transacción para poder actualizarlos desde dentro de la función.
        $registrados = 0;
        $corregidos = 0;

        // Usamos una transacción para que el registro del curso sea "todo o
        // nada": si algo falla a mitad de camino, no queda la lista a medias.
        DB::transaction(function () use ($data, $request, $curso, $inscripcionesActivas, &$registrados, &$corregidos) {
            foreach ($data['estados'] as $estudianteId => $estado) {
                $inscripcion = $inscripcionesActivas[$estudianteId] ?? null;
                if (! $inscripcion) {
                    // Si llega un alumno que no está inscrito en este curso
                    // (por ejemplo, un formulario manipulado), simplemente lo
                    // ignoramos. Es otra defensa de la validación por registro.
                    continue;
                }

                // Buscamos si el alumno ya tiene asistencia en esa fecha y turno,
                // ya que solo puede existir un registro por esa combinación.
                $existente = Asistencia::where('estudiante_id', $estudianteId)
                    ->whereDate('fecha', $data['fecha'])
                    ->where('turno', $data['turno'])
                    ->first();

                if ($existente) {
                    // Es una corrección. Comparamos el estado, el curso y la
                    // observación nuevos con los guardados, y solo si alguno
                    // cambió actualizamos el registro. Así no marcamos como
                    // "corregido" algo que en realidad quedó igual.
                    $cambios = collect(['estado', 'curso_id', 'observacion'])
                        ->mapWithKeys(fn ($campo) => [$campo => $campo === 'observacion'
                            ? ($data['observaciones'][$estudianteId] ?? null)
                            : ($campo === 'curso_id' ? $curso->id : $estado)])
                        ->filter(fn ($valor, $campo) => $existente->{$campo} !== $valor)
                        ->isNotEmpty();

                    if ($cambios) {
                        // Guardamos quién hizo la corrección para mantener la trazabilidad.
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

                // Si no había registro previo, creamos la asistencia nueva
                // vinculada a la inscripción y anotamos quién la registró.
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

        // Las correcciones se registran en la auditoría con un resumen del
        // curso, la fecha, el turno y cuántos registros se modificaron.
        if ($corregidos > 0) {
            AuditoriaService::registrar('asistencia.corregir', $curso, [
                'fecha' => $data['fecha'],
                'turno' => $data['turno'],
                'corregidos' => $corregidos,
            ]);
        }

        // Armamos el mensaje de confirmación y volvemos al listado con los
        // mismos filtros, para que el usuario vea de inmediato lo que guardó.
        $mensaje = "Asistencia registrada: {$registrados} nuevo(s)";
        if ($corregidos > 0) {
            $mensaje .= ", {$corregidos} corrección(es) con trazabilidad";
        }

        return redirect()
            ->route('asistencias.index', ['fecha' => $data['fecha'], 'curso_id' => $data['curso_id'], 'turno' => $data['turno']])
            ->with('success', $mensaje.'.');
    }

    /**
     * Genera el reporte de asistencia de un curso en un rango de fechas.
     *
     * El reporte trabaja con un denominador explícito: separa las ausencias
     * reales, los días sin registro y las jornadas en las que no correspondía
     * asistencia (sin clases), para que los porcentajes sean justos. Los
     * cálculos los hace `EstadisticaAsistencia`, el mismo servicio que usan
     * las exportaciones a PDF y Excel, de modo que los totales coinciden en
     * todos los formatos.
     *
     * @return View Vista `asistencias.reporte` con las filas por alumno y los totales.
     */
    public function reporte(Request $request): View
    {
        // Desde la decisión del 30/09/2026, el reporte por curso queda fuera
        // del alcance del responsable familiar, porque reúne datos de TODA la
        // clase. Él revisa la asistencia de sus hijos en el listado diario y
        // en el historial, no en este reporte institucional.
        abort_if($request->user()->esResponsableFamiliar(), 403);

        // El rango de fechas es obligatorio y "hasta" no puede ser anterior a "desde".
        $data = $request->validate([
            'curso_id' => ['required', 'exists:cursos,id'],
            'turno' => ['required', 'in:manana,tarde'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $curso = Curso::with('gestion')->findOrFail($data['curso_id']);

        // Validación por registro: un docente sin alcance institucional solo
        // puede sacar el reporte de los cursos que tiene asignados.
        $user = $request->user();
        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            abort_unless($user->tieneCursoAsignado($curso), 403);
        }

        // Las filas (detalle por alumno) y los totales del curso se calculan
        // en el servicio de estadística, así la lógica no se repite aquí.
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
     * Devuelve los cursos que el usuario actual puede ver en los filtros.
     *
     * Un docente solo ve los cursos que tiene asignados en la gestión actual;
     * el personal con alcance institucional ve todos los cursos activos. Al
     * responsable familiar se le muestra la lista general solo como filtro,
     * pero los registros que ve ya están limitados a sus hijos en index().
     *
     * @return \Illuminate\Support\Collection Colección de cursos ordenados.
     */
    private function cursosVisibles(Request $request)
    {
        $user = $request->user();
        $gestion = Gestion::actual();

        // Partimos de los cursos activos de la gestión actual, ordenados según
        // el orden definido por el colegio y luego por nombre.
        $query = Curso::where('activo', true)
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->orderBy('orden')
            ->orderBy('nombre');

        // Si es docente sin alcance institucional, restringimos a sus cursos asignados.
        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            $query->whereIn('cursos.id', \App\Support\Alcance::cursoIdsDocente($user));
        }

        return $query->get();
    }
}
