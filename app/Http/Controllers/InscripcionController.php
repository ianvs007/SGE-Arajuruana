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
 * Inscripciones por gestión (§7): separa la identidad del alumno de su matrícula.
 * Un alumno puede repetir curso en otra gestión sin perder historial ni crear
 * otra persona.
 */
class InscripcionController extends Controller
{
    public function index(Request $request): View
    {
        $gestion = $this->gestionSeleccionada($request);

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

        return view('inscripciones.index', [
            'inscripciones' => $inscripciones,
            'gestion' => $gestion,
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'cursos' => Curso::where('gestion_id', $gestion->id)->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

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

    public function store(Request $request): RedirectResponse
    {
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

        // Un alumno no se inscribe dos veces en la misma gestión (§7).
        $existe = Inscripcion::where('estudiante_id', $estudiante->id)
            ->where('gestion_id', $data['gestion_id'])
            ->exists();

        if ($existe) {
            return back()->withInput()->with('error', 'El estudiante ya tiene una inscripción en esta gestión. Edítela en su lugar.');
        }

        $inscripcion = Inscripcion::create($data + ['fecha_inscripcion' => $data['fecha_inscripcion'] ?? now()->toDateString()]);

        // Transición: mantiene curso_id del estudiante sincronizado con la gestión actual.
        if ((int) $data['gestion_id'] === (Gestion::actual()?->id ?? 0)) {
            $estudiante->update(['curso_id' => $data['curso_id']]);
        }

        AuditoriaService::registrar('inscripciones.crear', $inscripcion, [
            'estudiante' => $estudiante->codigo,
            'curso_id' => $data['curso_id'],
            'gestion_id' => $data['gestion_id'],
        ]);

        // §14: al inscribir (activa) se emiten las cuotas de aporte del alumno
        // desde el mes de inscripción. La obligación es del alumno; si el
        // operador no gestiona el módulo económico, Administración las genera
        // luego desde Cuotas → "Generar cuotas" (idempotente).
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

    public function edit(Request $request, Inscripcion $inscripcion): View
    {
        $inscripcion->load(['estudiante', 'curso', 'gestion']);

        return view('inscripciones.edit', [
            'inscripcion' => $inscripcion,
            'cursos' => Curso::where('gestion_id', $inscripcion->gestion_id)->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        $data = $request->validate([
            'curso_id' => ['required', Rule::exists('cursos', 'id')->where('gestion_id', $inscripcion->gestion_id)],
            'estado' => ['required', Rule::in(['activa', 'retirada', 'trasladada', 'cancelada'])],
            'fecha_inscripcion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'curso_id.exists' => 'El curso seleccionado no pertenece a la gestión de esta inscripción.',
        ]);

        $cursoAnterior = $inscripcion->curso_id;
        $inscripcion->update($data);

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
     * §7: la política de baja/traslado es trazable; no se borra la inscripción.
     * §14: retirar NO altera cobros automáticamente — las cuotas emitidas quedan
     * y Administración decide (eximir las pendientes con observación trazable).
     */
    public function destroy(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        abort_unless($request->user()->can('inscripciones.gestionar'), 403);

        if ($inscripcion->estado !== 'activa') {
            return back()->with('error', 'Solo se pueden cancelar inscripciones activas.');
        }

        $inscripcion->update(['estado' => 'cancelada']);
        AuditoriaService::registrar('inscripciones.cancelar', $inscripcion, ['estudiante_id' => $inscripcion->estudiante_id]);

        return back()->with('success', 'Inscripción cancelada (el historial se conserva). Las cuotas de aporte emitidas NO se modifican automáticamente; si corresponde, exímalas en Cuotas con la observación del caso.');
    }

    private function gestionSeleccionada(Request $request): Gestion
    {
        $id = $request->input('gestion_id');
        $gestion = $id ? Gestion::find($id) : Gestion::actual();

        abort_unless($gestion, 404, 'No hay ninguna gestión configurada.');

        return $gestion;
    }
}
