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
 * Controlador del módulo de incidencias (casos disciplinarios).
 *
 * Funciona de la siguiente manera:
 * - Solo Administración gestiona las incidencias (permiso
 *   "incidencias.gestionar"): las registra, modifica, les da seguimiento y
 *   las cierra.
 * - El Docente, con el permiso "incidencias.ver" (habilitado desde el
 *   30/09/2026), puede verificar los casos de los alumnos de sus cursos
 *   asignados, pero en modo de solo lectura y sin ver los confidenciales.
 * - Las categorías de incidencia son configurables y las mantiene
 *   Administración; no escribimos infracciones ni artículos de reglamento
 *   directamente en el código.
 * - Las incidencias confidenciales solo las ve Administración. No aparecen
 *   en reportes, paneles, búsquedas, notificaciones ni en el historial del
 *   alumno para ningún otro rol.
 */
class IncidenciaController extends Controller
{
    /**
     * Muestra el listado de incidencias con filtros.
     *
     * Se puede filtrar por estado de seguimiento, por categoría, por casos
     * confidenciales y buscar por datos del estudiante. Quien no gestiona
     * incidencias entra en modo solo lectura: no ve los casos confidenciales
     * y, si es docente, solo ve los de sus propios alumnos.
     *
     * @return View Vista con el listado de incidencias y los filtros.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        // Si el usuario no puede gestionar incidencias (por ejemplo, un docente), solo consulta.
        $soloLectura = ! $user->can('incidencias.gestionar');

        // Armamos la consulta aplicando cada filtro solo si viene en la petición.
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
            // Los casos confidenciales nunca se muestran a quien no gestiona incidencias.
            ->when($soloLectura, fn ($q) => $q->where('confidencial', false))
            // El docente solo puede verificar los casos de los alumnos de sus cursos asignados.
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

    /**
     * Muestra el formulario para registrar una nueva incidencia.
     *
     * Solo se listan los estudiantes activos y las categorías activas, para
     * no registrar casos sobre alumnos dados de baja o con categorías en desuso.
     *
     * @return View Vista del formulario de registro.
     */
    public function create(Request $request): View
    {
        return view('incidencias.create', [
            'estudiantes' => Estudiante::where('estado', 'activo')->orderBy('apellidos')->get(),
            'categorias' => IncidenciaCategoria::where('activa', true)->orderBy('nombre')->get(),
            'estados' => Incidencia::ESTADOS,
        ]);
    }

    /**
     * Registra una nueva incidencia.
     *
     * Se guarda junto con el usuario que la registró y se deja constancia en
     * la auditoría, incluyendo si el caso se marcó como confidencial.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        // Además de los datos del formulario, guardamos quién registró la incidencia.
        $incidencia = Incidencia::create($data + ['registrado_por' => $request->user()->id]);

        AuditoriaService::registrar('incidencias.crear', $incidencia, [
            'estudiante_id' => $incidencia->estudiante_id,
            'categoria_id' => $incidencia->categoria_id,
            'confidencial' => $incidencia->confidencial,
        ]);

        return redirect()->route('incidencias.index')->with('success', 'Incidencia registrada.');
    }

    /**
     * Muestra el formulario para editar una incidencia.
     *
     * Aquí se listan todos los estudiantes y todas las categorías (también
     * las inactivas), porque la incidencia pudo registrarse cuando todavía
     * estaban activos y debe poder seguir mostrándose correctamente.
     *
     * @return View Vista del formulario de edición.
     */
    public function edit(Incidencia $incidencia): View
    {
        return view('incidencias.edit', [
            'incidencia' => $incidencia,
            'estudiantes' => Estudiante::orderBy('apellidos')->get(),
            'categorias' => IncidenciaCategoria::orderBy('nombre')->get(),
            'estados' => Incidencia::ESTADOS,
        ]);
    }

    /**
     * Actualiza una incidencia (datos, seguimiento o cierre).
     *
     * En la auditoría se guarda cómo estaban el estado y la confidencialidad
     * antes y después del cambio, para saber quién cerró un caso o cambió su
     * nivel de privacidad.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function update(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $data = $this->validar($request);

        // Guardamos los valores anteriores antes de actualizar para poder compararlos.
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

    // ---------- Categorías configurables de incidencias ----------

    /**
     * Muestra la pantalla de administración de categorías de incidencia.
     *
     * @return View Vista con todas las categorías (activas e inactivas).
     */
    public function categorias(): View
    {
        return view('incidencias.categorias', [
            'categorias' => IncidenciaCategoria::orderBy('nombre')->get(),
        ]);
    }

    /**
     * Crea una nueva categoría de incidencia.
     *
     * El nombre no puede repetirse y la categoría se crea activa para que se
     * pueda usar de inmediato. La creación queda registrada en la auditoría.
     *
     * @return RedirectResponse Regreso a la pantalla de categorías.
     */
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

    /**
     * Actualiza el nombre, la descripción o el estado de una categoría.
     *
     * @return RedirectResponse Regreso a la pantalla de categorías.
     */
    public function updateCategoria(Request $request, IncidenciaCategoria $categoria): RedirectResponse
    {
        // El nombre debe seguir siendo único, sin compararlo con la propia categoría.
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('incidencias_categorias', 'nombre')->ignore($categoria->id)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activa' => ['nullable', 'boolean'],
        ]);

        // El checkbox "activa" no se envía cuando está desmarcado, por eso lo convertimos a booleano.
        $categoria->update($data + ['activa' => $request->boolean('activa')]);

        return back()->with('success', 'Categoría actualizada.');
    }

    /**
     * Desactiva una categoría de incidencia.
     *
     * En lugar de borrarla, la desactivamos: así las incidencias que ya la
     * usan conservan su referencia y la información sigue siendo trazable,
     * pero la categoría deja de ofrecerse para casos nuevos.
     *
     * @return RedirectResponse Regreso a la pantalla de categorías.
     */
    public function destroyCategoria(IncidenciaCategoria $categoria): RedirectResponse
    {
        $categoria->update(['activa' => false]);

        return back()->with('success', 'Categoría desactivada (las incidencias existentes la conservan).');
    }

    /**
     * Valida y prepara los datos de una incidencia.
     *
     * Se usa al registrar y al editar. La fecha no puede ser futura, la
     * categoría debe existir y el estado de seguimiento debe ser uno de los
     * definidos en Incidencia::ESTADOS. Además se ajustan algunos campos
     * antes de guardarlos.
     *
     * @return array Datos listos para guardar.
     */
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

        // Normalizamos el checkbox: si no está marcado lo guardamos como falso y no como nulo.
        $data['confidencial'] = $request->boolean('confidencial');

        // La columna "tipo" se conserva por compatibilidad con la estructura anterior
        // de la tabla, y la llenamos con el nombre de la categoría elegida.
        $data['tipo'] = IncidenciaCategoria::find($data['categoria_id'])?->nombre ?? 'sin categoria';

        return $data;
    }
}
