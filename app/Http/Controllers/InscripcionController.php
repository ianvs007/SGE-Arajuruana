<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Services\AuditoriaService;
use App\Support\Alcance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controlador de inscripciones por gestión.
 *
 * Atiende el módulo de inscripciones, disponible para quienes tienen el
 * permiso "inscripciones.gestionar" (personal de Administración/Secretaría).
 *
 * Decidimos separar la identidad del alumno (tabla de estudiantes) de su
 * matrícula en cada año escolar (tabla de inscripciones). Gracias a esto, un
 * alumno puede repetir curso o pasar al siguiente en otra gestión sin perder
 * su historial y sin tener que registrarlo como si fuera otra persona.
 */
class InscripcionController extends Controller
{
    /**
     * Muestra el listado de inscripciones de una gestión.
     *
     * Por defecto se muestra la gestión actual, pero se puede elegir otra.
     * También se puede filtrar por curso y buscar por nombre, apellido,
     * código o documento del estudiante.
     *
     * @return View Vista con las inscripciones, la gestión elegida y los filtros disponibles.
     */
    public function index(Request $request): View
    {
        $gestion = $this->gestionSeleccionada($request);

        // Consultamos las inscripciones de la gestión elegida, aplicando los filtros
        // opcionales de curso y de texto de búsqueda sobre los datos del estudiante.
        $inscripciones = Inscripcion::with(['estudiante.responsables', 'curso'])
            ->where('gestion_id', $gestion->id)
            ->when($request->input('curso_id'), fn ($q, $cursoId) => $q->where('curso_id', $cursoId))
            ->when($request->string('q')->toString(), function ($q) use ($request) {
                $texto = $request->string('q')->toString();
                $q->whereHas('estudiante', function ($e) use ($texto) {
                    $e->where('nombres', 'like', "%{$texto}%")
                        ->orWhere('apellidos', 'like', "%{$texto}%")
                        ->orWhere('codigo', 'like', "%{$texto}%")
                        ->orWhere('documento', 'like', "%{$texto}%");
                });
            })
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        // Además del listado, enviamos todas las gestiones y los cursos activos de la
        // gestión elegida para llenar los selectores de filtro.
        return view('inscripciones.index', [
            'inscripciones' => $inscripciones,
            'gestion' => $gestion,
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'cursos' => Curso::where('gestion_id', $gestion->id)->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Muestra el formulario para inscribir a un estudiante.
     *
     * Solo se ofrecen los cursos activos de la gestión elegida y los
     * estudiantes activos que todavía no tienen inscripción en esa gestión,
     * para no inscribir dos veces al mismo alumno.
     *
     * @return View Vista del formulario de inscripción.
     */
    public function create(Request $request): View
    {
        $gestion = $this->gestionSeleccionada($request);

        return view('inscripciones.create', [
            'gestion' => $gestion,
            'cursos' => Curso::where('gestion_id', $gestion->id)->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
            // Solo estudiantes activos sin inscripción en esta gestión.
            'estudiantes' => Estudiante::where('estado', 'activo')
                ->whereDoesntHave('inscripciones', fn ($q) => $q->where('gestion_id', $gestion->id))
                ->orderBy('apellidos')
                ->get(),
        ]);
    }

    /**
     * Registra una nueva inscripción.
     *
     * Se valida que el curso pertenezca a la gestión indicada y que el alumno
     * no tenga ya una inscripción en esa gestión. Si la inscripción es de la
     * gestión actual, se actualiza también el curso del estudiante. Por
     * último, si la inscripción queda activa y el usuario puede gestionar el
     * módulo económico, se generan sus cuotas de aporte.
     *
     * @return RedirectResponse Redirección al listado de la gestión o regreso con error.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validamos los datos; el curso tiene que existir y ser de la misma gestión elegida.
        $data = $request->validate([
            'gestion_id' => ['required', 'exists:gestiones,id'],
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'curso_id' => [
                'required',
                // El curso debe pertenecer a la gestión seleccionada.
                Rule::exists('cursos', 'id')->where('gestion_id', $request->input('gestion_id')),
            ],
            'estado' => ['required', Rule::in(['activa', 'retirada', 'trasladada', 'cancelada'])],
            'fecha_inscripcion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'curso_id.exists' => 'El curso seleccionado no pertenece a la gestión indicada.',
        ]);

        $estudiante = Estudiante::findOrFail($data['estudiante_id']);

        // Un alumno no puede tener dos inscripciones en la misma gestión; si ya existe
        // una, lo correcto es editarla en lugar de crear otra.
        $existe = Inscripcion::where('estudiante_id', $estudiante->id)
            ->where('gestion_id', $data['gestion_id'])
            ->exists();

        if ($existe) {
            return back()->withInput()->with('error', 'El estudiante ya tiene una inscripción en esta gestión. Edítela en su lugar.');
        }

        // Si no se indicó la fecha de inscripción, usamos la fecha de hoy.
        $inscripcion = Inscripcion::create($data + ['fecha_inscripcion' => $data['fecha_inscripcion'] ?? now()->toDateString()]);

        // Mientras dura la transición al nuevo modelo, el estudiante todavía guarda su curso
        // en el campo curso_id. Lo mantenemos sincronizado solo cuando la inscripción es
        // de la gestión actual, para no sobrescribirlo con cursos de otros años.
        if ((int) $data['gestion_id'] === (Gestion::actual()?->id ?? 0)) {
            $estudiante->update(['curso_id' => $data['curso_id']]);
        }

        AuditoriaService::registrar('inscripciones.crear', $inscripcion, [
            'estudiante' => $estudiante->codigo,
            'curso_id' => $data['curso_id'],
            'gestion_id' => $data['gestion_id'],
        ]);

        // Al inscribir a un alumno con estado activo se le emiten sus cuotas de aporte
        // desde el mes de inscripción, porque la obligación de pago es del alumno. Si quien
        // inscribe no tiene permiso sobre el módulo económico, no se generan aquí:
        // Administración las puede generar después desde Cuotas con "Generar cuotas",
        // que no duplica las que ya existen.
        if ($inscripcion->estado === 'activa' && $request->user()->can('aporte.cuotas.gestionar')) {
            $cuotas = \App\Services\AporteService::generarCuotasDeEstudiante($inscripcion, $request->user());
            if ($cuotas > 0) {
                AuditoriaService::registrar('aporte.cuotas.generar_inscripcion', $inscripcion, [
                    'estudiante' => $estudiante->codigo,
                    'cuotas' => $cuotas,
                ]);
            }
        }

        return redirect()->route('inscripciones.index', ['gestion_id' => $data['gestion_id']])
            ->with('success', 'Inscripción registrada.');
    }

    /**
     * Muestra el formulario para editar una inscripción.
     *
     * Solo se ofrecen los cursos de la misma gestión de la inscripción, ya
     * que no tiene sentido mover una inscripción a un curso de otro año.
     *
     * @return View Vista del formulario de edición.
     */
    public function edit(Request $request, Inscripcion $inscripcion): View
    {
        $inscripcion->load(['estudiante', 'curso', 'gestion']);

        return view('inscripciones.edit', [
            'inscripcion' => $inscripcion,
            'cursos' => Curso::where('gestion_id', $inscripcion->gestion_id)->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Actualiza una inscripción (curso, estado, fecha u observaciones).
     *
     * Si se cambia el curso de una inscripción de la gestión actual, también
     * se actualiza el curso del estudiante. El cambio queda registrado en la
     * auditoría con el curso anterior y el nuevo.
     *
     * @return RedirectResponse Redirección al listado de la gestión con mensaje de éxito.
     */
    public function update(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        // El nuevo curso tiene que pertenecer a la misma gestión de la inscripción.
        $data = $request->validate([
            'curso_id' => ['required', Rule::exists('cursos', 'id')->where('gestion_id', $inscripcion->gestion_id)],
            'estado' => ['required', Rule::in(['activa', 'retirada', 'trasladada', 'cancelada'])],
            'fecha_inscripcion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'curso_id.exists' => 'El curso seleccionado no pertenece a la gestión de esta inscripción.',
        ]);

        // Guardamos el curso anterior para compararlo y dejarlo en la auditoría.
        $cursoAnterior = $inscripcion->curso_id;
        $inscripcion->update($data);

        // Si es de la gestión actual y el curso cambió, sincronizamos el curso del estudiante.
        if ((int) $inscripcion->gestion_id === (Gestion::actual()?->id ?? 0) && $cursoAnterior !== (int) $data['curso_id']) {
            $inscripcion->estudiante->update(['curso_id' => $data['curso_id']]);
        }

        AuditoriaService::registrar('inscripciones.actualizar', $inscripcion, [
            'curso_anterior' => $cursoAnterior,
            'curso_nuevo' => $data['curso_id'],
            'estado' => $data['estado'],
        ]);

        return redirect()->route('inscripciones.index', ['gestion_id' => $inscripcion->gestion_id])
            ->with('success', 'Inscripción actualizada.');
    }

    /**
     * Cancela una inscripción activa.
     *
     * La inscripción no se borra: solo cambia su estado a "cancelada", para
     * que las bajas y traslados queden registrados en el historial. Además,
     * cancelar no modifica automáticamente los cobros: las cuotas de aporte
     * ya emitidas se mantienen y es Administración quien decide si eximir las
     * pendientes, dejando una observación que explique el caso.
     *
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function destroy(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        abort_unless($request->user()->can('inscripciones.gestionar'), 403);

        // Solo tiene sentido cancelar una inscripción que todavía está activa.
        if ($inscripcion->estado !== 'activa') {
            return back()->with('error', 'Solo se pueden cancelar inscripciones activas.');
        }

        $inscripcion->update(['estado' => 'cancelada']);
        AuditoriaService::registrar('inscripciones.cancelar', $inscripcion, ['estudiante_id' => $inscripcion->estudiante_id]);

        return back()->with('success', 'Inscripción cancelada (el historial se conserva). Las cuotas de aporte emitidas NO se modifican automáticamente; si corresponde, exímalas en Cuotas con la observación del caso.');
    }

    /**
     * Determina con qué gestión se está trabajando.
     *
     * Si en la petición viene un "gestion_id" se usa esa gestión; si no, se
     * toma la gestión marcada como actual. Si no hay ninguna configurada se
     * responde con un error 404, porque sin gestión no se puede inscribir.
     *
     * @return Gestion Gestión seleccionada.
     */
    private function gestionSeleccionada(Request $request): Gestion
    {
        $id = $request->input('gestion_id');
        $gestion = $id ? Gestion::find($id) : Gestion::actual();

        abort_unless($gestion, 404, 'No hay ninguna gestión configurada.');

        return $gestion;
    }
}
