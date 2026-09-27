<?php

namespace App\Http\Controllers;

use App\Models\Gestion;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestión académica configurable (§4): Administración define gestiones y
 * cuál es la actual. Nada de años queda fijado en el código.
 */
class GestionController extends Controller
{
    public function index(): View
    {
        $gestiones = Gestion::withCount('cursos')->orderByDesc('anio')->paginate(15);

        return view('gestiones.index', compact('gestiones'));
    }

    public function create(): View
    {
        return view('gestiones.create', [
            'gestion' => new Gestion(['es_actual' => false, 'activa' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        $gestion = Gestion::create($data);
        AuditoriaService::registrar('gestiones.crear', $gestion, ['anio' => $gestion->anio]);

        return redirect()->route('gestiones.index')->with('success', 'Gestión creada.');
    }

    public function edit(Gestion $gestion): View
    {
        return view('gestiones.edit', ['gestion' => $gestion]);
    }

    public function update(Request $request, Gestion $gestion): RedirectResponse
    {
        $data = $this->validar($request, $gestion->id);

        $gestion->update($data);
        AuditoriaService::registrar('gestiones.actualizar', $gestion, ['anio' => $gestion->anio]);

        return redirect()->route('gestiones.index')->with('success', 'Gestión actualizada.');
    }

    /** Marca esta gestión como actual y desmarca las demás (§4). */
    public function marcarActual(Gestion $gestion): RedirectResponse
    {
        DB::transaction(function () use ($gestion) {
            Gestion::where('id', '!=', $gestion->id)->update(['es_actual' => false]);
            $gestion->update(['es_actual' => true, 'activa' => true]);
        });

        AuditoriaService::registrar('gestiones.marcar_actual', $gestion, ['anio' => $gestion->anio]);

        return back()->with('success', "La gestión {$gestion->nombre} ahora es la actual.");
    }

    public function destroy(Gestion $gestion): RedirectResponse
    {
        if ($gestion->cursos()->exists() || $gestion->inscripciones()->exists()) {
            return back()->with('error', 'No se puede eliminar una gestión con cursos o inscripciones. Desactívela en su lugar.');
        }

        $gestion->delete();

        return redirect()->route('gestiones.index')->with('success', 'Gestión eliminada.');
    }

    private function validar(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('gestiones', 'anio')->ignore($ignoreId)],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'activa' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [], ['anio' => 'año']);
    }
}
