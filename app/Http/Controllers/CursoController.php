<?php

namespace App\Http\Controllers;

use App\Models\CalendarioExcepcion;
use App\Models\Curso;
use App\Models\Gestion;
use App\Models\HorarioCurso;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cursos por gestión, con horarios y calendario (§4).
 * La lista efectiva de cursos, días y horarios se carga por configuración,
 * no por programación.
 */
class CursoController extends Controller
{
    public function index(Request $request): View
    {
        $gestion = $this->gestionSeleccionada($request);

        $cursos = Curso::withCount(['inscripciones as inscritos_count' => fn ($q) => $q->where('estado', 'activa')])
            ->where('gestion_id', $gestion->id)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(20);

        return view('cursos.index', [
            'cursos' => $cursos,
            'gestion' => $gestion,
            'gestiones' => Gestion::orderByDesc('anio')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $gestion = $this->gestionSeleccionada($request);

        return view('cursos.create', [
            'curso' => new Curso(['gestion_id' => $gestion->id, 'turno' => 'manana', 'activo' => true]),
            'gestion' => $gestion,
            'niveles' => $this->niveles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        $curso = Curso::create($data + ['anio_lectivo' => Gestion::find($data['gestion_id'])?->anio ?? (int) date('Y')]);
        AuditoriaService::registrar('cursos.crear', $curso, ['nombre' => $curso->nombre]);

        return redirect()->route('cursos.index', ['gestion_id' => $curso->gestion_id])
            ->with('success', 'Curso creado.');
    }

    public function edit(Curso $curso): View
    {
        $curso->load(['horarios', 'docentes', 'gestion']);

        return view('cursos.edit', [
            'curso' => $curso,
            'gestion' => $curso->gestion,
            'niveles' => $this->niveles(),
            'docentesDisponibles' => User::role('Docente')->orderBy('name')->get(),
            'horarios' => $curso->horarios()->get(),
            'excepciones' => CalendarioExcepcion::where(function ($q) use ($curso) {
                $q->where('curso_id', $curso->id)->orWhere(fn ($q2) => $q2->whereNull('curso_id')->where('gestion_id', $curso->gestion_id));
            })->orderBy('fecha')->get(),
        ]);
    }

    public function update(Request $request, Curso $curso): RedirectResponse
    {
        $data = $this->validar($request, $curso->id);

        $curso->update($data);
        AuditoriaService::registrar('cursos.actualizar', $curso, ['nombre' => $curso->nombre]);

        return redirect()->route('cursos.index', ['gestion_id' => $curso->gestion_id])
            ->with('success', 'Curso actualizado.');
    }

    public function destroy(Curso $curso): RedirectResponse
    {
        if ($curso->inscripciones()->where('estado', 'activa')->exists()) {
            return back()->with('error', 'El curso tiene inscripciones activas. Desactívelo en su lugar.');
        }

        $curso->update(['activo' => false]);

        return back()->with('success', 'Curso desactivado.');
    }

    // --- Horarios (vigentes desde una fecha; no reinterpretan el pasado §4) ---

    public function storeHorario(Request $request, Curso $curso): RedirectResponse
    {
        $data = $request->validate([
            'dia_semana' => ['required', 'integer', 'between:1,7'],
            'turno' => ['required', Rule::in(array_keys(HorarioCurso::TURNOS))],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        $horario = $curso->horarios()->create($data + ['activo' => true, 'vigente_desde' => now()]);
        AuditoriaService::registrar('horarios.crear', $horario, ['curso_id' => $curso->id]);

        return back()->with('success', 'Horario agregado.');
    }

    /**
     * Desactivar un horario cierra su ventana de vigencia (§4/§20.5):
     * las asistencias pasadas conservan el significado que tenían entonces.
     * No se borra físicamente.
     */
    public function destroyHorario(Curso $curso, HorarioCurso $horario): RedirectResponse
    {
        abort_unless($horario->curso_id === $curso->id, 404);

        $horario->update(['activo' => false, 'vigente_hasta' => now()]);
        AuditoriaService::registrar('horarios.desactivar', $horario, ['curso_id' => $curso->id]);

        return back()->with('success', 'Horario desactivado: solo afecta fechas futuras; el historial pasado se conserva.');
    }

    // --- Asignación de docentes (§4, §5) ---

    public function asignarDocente(Request $request, Curso $curso): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $curso->docentes()->syncWithoutDetaching([
            $data['user_id'] => ['gestion_id' => $curso->gestion_id, 'rol_docente' => 'docente_aula'],
        ]);
        AuditoriaService::registrar('cursos.asignar_docente', $curso, ['user_id' => $data['user_id']]);

        return back()->with('success', 'Docente asignado al curso.');
    }

    public function quitarDocente(Curso $curso, User $docente): RedirectResponse
    {
        $curso->docentes()->detach($docente->id);

        return back()->with('success', 'Docente retirado del curso.');
    }

    // --- Calendario: jornadas sin clases y excepciones (§4, §9) ---

    public function storeExcepcion(Request $request, Curso $curso): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', Rule::in(array_keys(CalendarioExcepcion::TIPOS))],
            'motivo' => ['nullable', 'string', 'max:255'],
            'aplica_solo_curso' => ['nullable', 'boolean'],
        ]);

        $excepcion = CalendarioExcepcion::create([
            'gestion_id' => $curso->gestion_id,
            'curso_id' => $request->boolean('aplica_solo_curso') ? $curso->id : null,
            'fecha' => $data['fecha'],
            'tipo' => $data['tipo'],
            'motivo' => $data['motivo'] ?? null,
        ]);
        AuditoriaService::registrar('calendario.excepcion', $excepcion, ['fecha' => $data['fecha'], 'tipo' => $data['tipo']]);

        return back()->with('success', 'Jornada registrada en el calendario.');
    }

    public function destroyExcepcion(Curso $curso, CalendarioExcepcion $excepcion): RedirectResponse
    {
        $excepcion->delete();

        return back()->with('success', 'Jornada eliminada del calendario.');
    }

    private function gestionSeleccionada(Request $request): Gestion
    {
        $id = $request->input('gestion_id');
        $gestion = $id ? Gestion::find($id) : Gestion::actual();

        abort_unless($gestion, 404, 'No hay ninguna gestión configurada. Cree una en Configuración → Gestiones.');

        return $gestion;
    }

    private function niveles(): array
    {
        return ['Inicial' => 'Inicial', 'Primaria' => 'Primaria', 'Secundaria' => 'Secundaria'];
    }

    private function validar(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'gestion_id' => ['required', 'exists:gestiones,id'],
            'nombre' => ['required', 'string', 'max:120'],
            'nivel' => ['nullable', 'string', 'max:60'],
            'grado' => ['nullable', 'string', 'max:60'],
            'paralelo' => ['nullable', 'string', 'max:10'],
            'turno' => ['nullable', Rule::in(array_keys(Curso::TURNOS))],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['nullable', 'boolean'],
        ]);
    }
}
