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
 * Controlador de gestiones académicas (años escolares).
 *
 * Pertenece al módulo de configuración. Permite a Administración (usuarios
 * con el permiso "configuracion.gestionar") crear, editar y eliminar las
 * gestiones, además de indicar cuál es la gestión actual. Lo hicimos
 * configurable para que ningún año quede escrito directamente en el código:
 * cuando empiece un nuevo año escolar basta con registrarlo desde aquí.
 */
class GestionController extends Controller
{
    /**
     * Muestra el listado de gestiones registradas.
     *
     * Se ordenan de la más reciente a la más antigua y se incluye la cantidad
     * de cursos de cada una, para tener una idea rápida de su uso.
     *
     * @return View Vista con el listado paginado de gestiones.
     */
    public function index(): View
    {
        $gestiones = Gestion::withCount('cursos')->orderByDesc('anio')->paginate(15);

        return view('gestiones.index', compact('gestiones'));
    }

    /**
     * Muestra el formulario para registrar una nueva gestión.
     *
     * Se envía una gestión vacía con valores por defecto: activa, pero no
     * marcada como actual, porque esa decisión se toma por separado.
     *
     * @return View Vista del formulario de creación.
     */
    public function create(): View
    {
        return view('gestiones.create', [
            'gestion' => new Gestion(['es_actual' => false, 'activa' => true]),
        ]);
    }

    /**
     * Guarda una nueva gestión en la base de datos.
     *
     * Después de crearla se registra la acción en la auditoría, para saber
     * quién y cuándo dio de alta cada año escolar.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        // Creamos la gestión y dejamos constancia en la bitácora de auditoría.
        $gestion = Gestion::create($data);
        AuditoriaService::registrar('gestiones.crear', $gestion, ['anio' => $gestion->anio]);

        return redirect()->route('gestiones.index')->with('success', 'Gestión creada.');
    }

    /**
     * Muestra el formulario para editar una gestión existente.
     *
     * @return View Vista del formulario de edición.
     */
    public function edit(Gestion $gestion): View
    {
        return view('gestiones.edit', ['gestion' => $gestion]);
    }

    /**
     * Actualiza los datos de una gestión.
     *
     * Al validar se le pasa el ID de la gestión para que la regla de año único
     * no la compare consigo misma.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function update(Request $request, Gestion $gestion): RedirectResponse
    {
        $data = $this->validar($request, $gestion->id);

        // Guardamos los cambios y los registramos en la auditoría.
        $gestion->update($data);
        AuditoriaService::registrar('gestiones.actualizar', $gestion, ['anio' => $gestion->anio]);

        return redirect()->route('gestiones.index')->with('success', 'Gestión actualizada.');
    }

    /**
     * Marca una gestión como la gestión actual del sistema.
     *
     * Solo puede existir una gestión actual a la vez, porque muchos módulos
     * (inscripciones, cursos, asistencia, reportes) trabajan sobre ella. Por
     * eso primero se desmarcan todas las demás y luego se marca la elegida.
     *
     * @return RedirectResponse Regreso a la página anterior con mensaje de éxito.
     */
    public function marcarActual(Gestion $gestion): RedirectResponse
    {
        // Usamos una transacción para que ambos cambios se hagan juntos: si algo falla,
        // no puede quedar el sistema sin gestión actual o con dos gestiones actuales.
        DB::transaction(function () use ($gestion) {
            Gestion::where('id', '!=', $gestion->id)->update(['es_actual' => false]);
            $gestion->update(['es_actual' => true, 'activa' => true]);
        });

        AuditoriaService::registrar('gestiones.marcar_actual', $gestion, ['anio' => $gestion->anio]);

        return back()->with('success', "La gestión {$gestion->nombre} ahora es la actual.");
    }

    /**
     * Elimina una gestión.
     *
     * Solo se permite borrar gestiones que no tengan cursos ni inscripciones,
     * para no perder información histórica de los estudiantes. Si ya tiene
     * datos asociados, se sugiere desactivarla en lugar de eliminarla.
     *
     * @return RedirectResponse Redirección al listado o regreso con un error.
     */
    public function destroy(Gestion $gestion): RedirectResponse
    {
        // Protegemos la integridad de los datos: no se elimina una gestión que ya se está usando.
        if ($gestion->cursos()->exists() || $gestion->inscripciones()->exists()) {
            return back()->with('error', 'No se puede eliminar una gestión con cursos o inscripciones. Desactívela en su lugar.');
        }

        $gestion->delete();

        return redirect()->route('gestiones.index')->with('success', 'Gestión eliminada.');
    }

    /**
     * Valida los datos del formulario de gestión.
     *
     * Lo usamos tanto al crear como al editar para no repetir las reglas. El
     * año debe ser único entre las gestiones y estar en un rango razonable, y
     * la fecha de fin no puede ser anterior a la de inicio.
     *
     * @param  int|null  $ignoreId  ID de la gestión que se está editando, para excluirla de la regla de año único.
     * @return array Datos ya validados.
     */
    private function validar(Request $request, ?int $ignoreId = null): array
    {
        // El último arreglo renombra el campo "anio" como "año" en los mensajes de error.
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
