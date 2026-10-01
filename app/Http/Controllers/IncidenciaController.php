<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Incidencia;
use App\Models\IncidenciaCategoria;
use App\Services\AuditoriaService;
use App\Support\Alcance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Incidencias (§11).
 *
 * - Gestionadas solo por Administración (decisión confirmada): registro,
 *   modificación, seguimiento y cierre.
 * - Consulta (`incidencias.ver`, 30/09/2026): el Docente VERIFICA casos
 *   disciplinarios de los alumnos de sus cursos asignados, en solo lectura
 *   y sin casos confidenciales (§11).
 * - Categorías configurables: Administración las mantiene; no se inventan
 *   infracciones ni artículos en el código.
 * - Confidenciales (decisión confirmada): SOLO Administración las ve. No se
 *   filtran por reportes, paneles, búsquedas, notificaciones ni historial
 *   (§11, §20.8).
 */
class IncidenciaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        // Quien solo verifica (Docente, 30/09/2026) consulta en solo lectura.
        $soloLectura = ! $user->can('incidencias.gestionar');

        $incidencias = Incidencia::with(['estudiante', 'categoria', 'registrador'])
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado_seguimiento', $estado))
            ->when($request->input('categoria_id'), fn ($q, $id) => $q->where('categoria_id', $id))
            ->when($request->boolean('solo_confidenciales'), fn ($q) => $q->where('confidencial', true))
            ->when($request->string('q')->toString(), function ($q) use ($request) {
                $texto = $request->string('q')->toString();
                $q->whereHas('estudiante', function ($e) use ($texto) {
                    $e->where('nombres', 'like', "%{$texto}%")
                        ->orWhere('apellidos', 'like', "%{$texto}%")
                        ->orWhere('codigo', 'like', "%{$texto}%");
                });
            })
            // §11: los casos confidenciales nunca se listan a quien no los gestiona.
            ->when($soloLectura, fn ($q) => $q->where('confidencial', false))
            // §6: el docente verifica solo los casos de alumnos de sus cursos.
            ->when(
                $user->esDocente() && ! $user->tieneAlcanceInstitucional(),
                fn ($q) => $q->whereIn('estudiante_id', Alcance::estudiantes($user)->pluck('estudiantes.id'))
            )
            ->latest('fecha')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('incidencias.index', [
            'incidencias' => $incidencias,
            'estados' => Incidencia::ESTADOS,
            'categorias' => IncidenciaCategoria::where('activa', true)->orderBy('nombre')->get(),
            'soloLectura' => $soloLectura,
        ]);
    }

    public function create(Request $request): View
    {
        return view('incidencias.create', [
            'estudiantes' => Estudiante::where('estado', 'activo')->orderBy('apellidos')->get(),
            'categorias' => IncidenciaCategoria::where('activa', true)->orderBy('nombre')->get(),
            'estados' => Incidencia::ESTADOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        $incidencia = Incidencia::create($data + ['registrado_por' => $request->user()->id]);

        AuditoriaService::registrar('incidencias.crear', $incidencia, [
            'estudiante_id' => $incidencia->estudiante_id,
            'categoria_id' => $incidencia->categoria_id,
            'confidencial' => $incidencia->confidencial,
        ]);

        return redirect()->route('incidencias.index')->with('success', 'Incidencia registrada.');
    }

    public function edit(Incidencia $incidencia): View
    {
        return view('incidencias.edit', [
            'incidencia' => $incidencia,
            'estudiantes' => Estudiante::orderBy('apellidos')->get(),
            'categorias' => IncidenciaCategoria::orderBy('nombre')->get(),
            'estados' => Incidencia::ESTADOS,
        ]);
    }

    public function update(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $data = $this->validar($request);

        $antes = ['estado' => $incidencia->estado_seguimiento, 'confidencial' => $incidencia->confidencial];
        $incidencia->update($data);

        AuditoriaService::registrar('incidencias.actualizar', $incidencia, [
            'estado_antes' => $antes['estado'],
            'estado_despues' => $incidencia->estado_seguimiento,
            'confidencial_antes' => $antes['confidencial'],
            'confidencial_despues' => $incidencia->confidencial,
        ]);

        return redirect()->route('incidencias.index')->with('success', 'Incidencia actualizada.');
    }

    // ---------- Categorías configurables (§11) ----------

    public function categorias(): View
    {
        return view('incidencias.categorias', [
            'categorias' => IncidenciaCategoria::orderBy('nombre')->get(),
        ]);
    }

    public function storeCategoria(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:incidencias_categorias,nombre'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $categoria = IncidenciaCategoria::create($data + ['activa' => true]);
        AuditoriaService::registrar('incidencias.categoria.crear', $categoria, ['nombre' => $data['nombre']]);

        return back()->with('success', 'Categoría creada.');
    }

    public function updateCategoria(Request $request, IncidenciaCategoria $categoria): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('incidencias_categorias', 'nombre')->ignore($categoria->id)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activa' => ['nullable', 'boolean'],
        ]);

        $categoria->update($data + ['activa' => $request->boolean('activa')]);

        return back()->with('success', 'Categoría actualizada.');
    }

    /**
     * Desactivar en vez de borrar: conserva la referencia de incidencias
     * existentes (§7: inactivación y correcciones trazables).
     */
    public function destroyCategoria(IncidenciaCategoria $categoria): RedirectResponse
    {
        $categoria->update(['activa' => false]);

        return back()->with('success', 'Categoría desactivada (las incidencias existentes la conservan).');
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'categoria_id' => ['required', 'exists:incidencias_categorias,id'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'medida_accion' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'confidencial' => ['nullable', 'boolean'],
            'estado_seguimiento' => ['required', Rule::in(array_keys(Incidencia::ESTADOS))],
        ]);

        // Normaliza el checkbox: no marcado = false (no null).
        $data['confidencial'] = $request->boolean('confidencial');

        // 'tipo' se conserva por compatibilidad con la columna existente:
        // se rellena con el nombre de la categoría.
        $data['tipo'] = IncidenciaCategoria::find($data['categoria_id'])?->nombre ?? 'sin categoria';

        return $data;
    }
}
