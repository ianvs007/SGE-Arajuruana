<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Support\Alcance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EstudianteController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $q = $request->string('q')->toString();

        // §5/§6: el listado se acota por rol (denegado por defecto).
        $query = Alcance::estudiantes($user)->with(['curso', 'padres']);

        $estudiantes = $query
            ->when($q, function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('nombres', 'like', "%{$q}%")
                        ->orWhere('apellidos', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%")
                        ->orWhere('documento', 'like', "%{$q}%");
                });
            })
            ->orderBy('apellidos')
            ->paginate(12)
            ->withQueryString();

        return view('estudiantes.index', compact('estudiantes', 'q'));
    }

    public function create(): View
    {
        return view('estudiantes.create', [
            'cursos' => Curso::where('activo', true)->orderBy('nombre')->get(),
            'padres' => User::role(User::ROL_RESPONSABLE)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = collect($this->validated($request))->except('padres')->all();
        $estudiante = Estudiante::create($data);
        $this->syncPadres($request, $estudiante);

        return redirect()->route('estudiantes.index')->with('success', 'Estudiante registrado.');
    }

    public function show(Request $request, Estudiante $estudiante): View
    {
        $this->authorizeAcceso($request, $estudiante);
        $estudiante->load(['curso', 'padres', 'asistencias' => fn ($q) => $q->latest('fecha')->take(10), 'incidencias' => fn ($q) => $q->latest('fecha')->take(10), 'citaciones' => fn ($q) => $q->latest('fecha')->take(10), 'salidas' => fn ($q) => $q->latest('fecha')->take(10)]);

        return view('estudiantes.show', compact('estudiante'));
    }

    public function edit(Request $request, Estudiante $estudiante): View
    {
        $this->authorizeAcceso($request, $estudiante);

        return view('estudiantes.edit', [
            'estudiante' => $estudiante->load('padres'),
            'cursos' => Curso::where('activo', true)->orderBy('nombre')->get(),
            'padres' => User::role(User::ROL_RESPONSABLE)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Estudiante $estudiante): RedirectResponse
    {
        $data = collect($this->validated($request, $estudiante->id))->except('padres')->all();
        $estudiante->update($data);
        $this->syncPadres($request, $estudiante);

        return redirect()->route('estudiantes.index')->with('success', 'Estudiante actualizado.');
    }

    /**
     * §7: no se elimina físicamente información con movimientos asociados.
     * La baja se implementa como inactivación trazable.
     */
    public function destroy(Request $request, Estudiante $estudiante): RedirectResponse
    {
        $this->authorizeAcceso($request, $estudiante);

        $tieneMovimientos = $estudiante->asistencias()->exists()
            || $estudiante->incidencias()->exists()
            || $estudiante->citaciones()->exists()
            || $estudiante->salidas()->exists();

        if ($tieneMovimientos) {
            $estudiante->update(['estado' => 'inactivo']);
            AuditoriaService::registrar('estudiantes.inactivar', $estudiante, ['codigo' => $estudiante->codigo]);

            return redirect()->route('estudiantes.index')
                ->with('success', 'El estudiante tiene registros asociados: fue inactivado (no eliminado). Su historial se conserva.');
        }

        $codigo = $estudiante->codigo;
        $estudiante->delete();
        AuditoriaService::registrar('estudiantes.eliminar_sin_movimientos', null, ['codigo' => $codigo]);

        return redirect()->route('estudiantes.index')->with('success', 'Estudiante eliminado (no tenía movimientos asociados).');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'unique:estudiantes,codigo,'.($ignoreId ?? 'NULL').',id'],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'documento' => ['nullable', 'string', 'max:30', 'unique:estudiantes,documento,'.($ignoreId ?? 'NULL').',id'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sexo' => ['nullable', 'string', 'max:20'],
            'curso_id' => ['nullable', 'exists:cursos,id'],
            'estado' => ['required', Rule::in(array_keys(Estudiante::ESTADOS))],
            'observaciones' => ['nullable', 'string'],
            'padres' => ['nullable', 'array'],
            'padres.*' => ['exists:users,id'],
        ]);
    }

    private function syncPadres(Request $request, Estudiante $estudiante): void
    {
        $padres = collect($request->input('padres', []))->filter()->unique()->values();
        $sync = [];
        foreach ($padres as $i => $padreId) {
            $sync[$padreId] = [
                'parentesco' => 'responsable',
                'es_principal' => $i === 0,
            ];
        }
        $estudiante->padres()->sync($sync);
    }

    /**
     * §6: validación por registro en el servidor. Alterar un identificador en la URL
     * no permite consultar datos ajenos.
     */
    private function authorizeAcceso(Request $request, Estudiante $estudiante): void
    {
        abort_unless(Alcance::puedeVerEstudiante($request->user(), $estudiante), 403);
    }
}
