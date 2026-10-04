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

/**
 * Controlador del módulo de estudiantes.
 *
 * Permite listar, consultar, registrar, editar y dar de baja a los alumnos
 * de la unidad educativa, además de vincularlos con sus responsables
 * familiares (padres, madres o tutores).
 *
 * El listado y la ficha del alumno los pueden ver quienes tienen el permiso
 * "estudiantes.ver" o "estudiantes.gestionar", pero cada rol solo ve a los
 * alumnos de su alcance: el personal institucional ve a todos, el docente a
 * los de sus cursos y el responsable familiar a sus representados. Crear,
 * editar o eliminar requiere "estudiantes.gestionar".
 */
class EstudianteController extends Controller
{
    /**
     * Muestra el listado de estudiantes con un buscador.
     *
     * La consulta parte de Alcance::estudiantes(), que ya restringe los
     * alumnos según el rol del usuario; si el rol no se reconoce, no se
     * devuelve ningún registro. Sobre eso se aplica la búsqueda opcional.
     *
     * @return View Vista con el listado paginado de estudiantes.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $q = $request->string('q')->toString();

        // Partimos solo de los estudiantes que este usuario puede ver según su rol.
        $query = Alcance::estudiantes($user)->with(['curso', 'padres']);

        // Si hay texto de búsqueda, filtramos por nombres, apellidos, código o documento.
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

    /**
     * Muestra el formulario para registrar un nuevo estudiante.
     *
     * Se cargan los cursos activos y la lista de responsables familiares para
     * poder asignarle un curso y vincularlo con sus padres o tutores.
     *
     * @return View Vista del formulario de registro.
     */
    public function create(): View
    {
        return view('estudiantes.create', [
            'cursos' => Curso::where('activo', true)->orderBy('nombre')->get(),
            'padres' => User::role(User::ROL_RESPONSABLE)->orderBy('name')->get(),
        ]);
    }

    /**
     * Guarda un nuevo estudiante y lo vincula con sus responsables.
     *
     * Los responsables no son una columna del estudiante sino una relación
     * aparte, por eso se quitan de los datos validados antes de crear el
     * registro y luego se sincronizan por separado.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function store(Request $request): RedirectResponse
    {
        // Separamos los padres del resto de los datos, ya que se guardan en otra tabla.
        $data = collect($this->validated($request))->except('padres')->all();
        $estudiante = Estudiante::create($data);
        $this->syncPadres($request, $estudiante);

        return redirect()->route('estudiantes.index')->with('success', 'Estudiante registrado.');
    }

    /**
     * Muestra la ficha de un estudiante.
     *
     * Además de sus datos, se cargan los últimos 10 registros de asistencia,
     * incidencias, citaciones y salidas para tener un resumen rápido.
     *
     * @return View Vista con la ficha del estudiante.
     */
    public function show(Request $request, Estudiante $estudiante): View
    {
        // Comprobamos que el alumno esté dentro del alcance del usuario antes de mostrar nada.
        $this->authorizeAcceso($request, $estudiante);
        $estudiante->load(['curso', 'padres', 'asistencias' => fn ($q) => $q->latest('fecha')->take(10), 'incidencias' => fn ($q) => $q->latest('fecha')->take(10), 'citaciones' => fn ($q) => $q->latest('fecha')->take(10), 'salidas' => fn ($q) => $q->latest('fecha')->take(10)]);

        return view('estudiantes.show', compact('estudiante'));
    }

    /**
     * Muestra el formulario para editar un estudiante.
     *
     * @return View Vista de edición con el estudiante, los cursos activos y los responsables.
     */
    public function edit(Request $request, Estudiante $estudiante): View
    {
        $this->authorizeAcceso($request, $estudiante);

        return view('estudiantes.edit', [
            'estudiante' => $estudiante->load('padres'),
            'cursos' => Curso::where('activo', true)->orderBy('nombre')->get(),
            'padres' => User::role(User::ROL_RESPONSABLE)->orderBy('name')->get(),
        ]);
    }

    /**
     * Actualiza los datos de un estudiante y sus responsables.
     *
     * Se pasa el ID del estudiante a la validación para que las reglas de
     * código y documento únicos no lo comparen consigo mismo.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function update(Request $request, Estudiante $estudiante): RedirectResponse
    {
        $data = collect($this->validated($request, $estudiante->id))->except('padres')->all();
        $estudiante->update($data);
        $this->syncPadres($request, $estudiante);

        return redirect()->route('estudiantes.index')->with('success', 'Estudiante actualizado.');
    }

    /**
     * Da de baja a un estudiante.
     *
     * No eliminamos físicamente a un alumno que ya tiene movimientos
     * (asistencias, incidencias, citaciones o salidas), porque se perdería su
     * historial. En ese caso solo se cambia su estado a "inactivo". Únicamente
     * si no tiene ningún registro asociado se borra de la base de datos.
     *
     * @return RedirectResponse Redirección al listado con el resultado de la baja.
     */
    public function destroy(Request $request, Estudiante $estudiante): RedirectResponse
    {
        $this->authorizeAcceso($request, $estudiante);

        // Verificamos si el alumno tiene algún registro relacionado en los módulos del sistema.
        $tieneMovimientos = $estudiante->asistencias()->exists()
            || $estudiante->incidencias()->exists()
            || $estudiante->citaciones()->exists()
            || $estudiante->salidas()->exists();

        // Si tiene movimientos, lo inactivamos y lo dejamos registrado en la auditoría.
        if ($tieneMovimientos) {
            $estudiante->update(['estado' => 'inactivo']);
            AuditoriaService::registrar('estudiantes.inactivar', $estudiante, ['codigo' => $estudiante->codigo]);

            return redirect()->route('estudiantes.index')
                ->with('success', 'El estudiante tiene registros asociados: fue inactivado (no eliminado). Su historial se conserva.');
        }

        // Si no tiene movimientos, lo eliminamos; guardamos antes su código para la auditoría.
        $codigo = $estudiante->codigo;
        $estudiante->delete();
        AuditoriaService::registrar('estudiantes.eliminar_sin_movimientos', null, ['codigo' => $codigo]);

        return redirect()->route('estudiantes.index')->with('success', 'Estudiante eliminado (no tenía movimientos asociados).');
    }

    /**
     * Valida los datos del formulario de estudiante.
     *
     * Se usa tanto al registrar como al editar. El código y el documento del
     * alumno deben ser únicos; al editar se excluye el propio registro. El
     * estado debe ser uno de los definidos en Estudiante::ESTADOS y cada
     * responsable seleccionado debe existir como usuario.
     *
     * @param  int|null  $ignoreId  ID del estudiante que se edita, para excluirlo de las reglas de unicidad.
     * @return array Datos validados.
     */
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

    /**
     * Sincroniza los responsables familiares vinculados al estudiante.
     *
     * Se limpian los valores vacíos y repetidos de la lista enviada y se
     * guardan en la tabla intermedia. El primer responsable de la lista queda
     * marcado como principal, que es a quien se considera el contacto
     * principal de la familia.
     */
    private function syncPadres(Request $request, Estudiante $estudiante): void
    {
        // Quitamos elementos vacíos y duplicados para no crear vínculos repetidos.
        $padres = collect($request->input('padres', []))->filter()->unique()->values();
        $sync = [];
        foreach ($padres as $i => $padreId) {
            $sync[$padreId] = [
                'parentesco' => 'responsable',
                'es_principal' => $i === 0,
            ];
        }
        // sync() deja exactamente estos vínculos: agrega los nuevos y quita los que ya no están.
        $estudiante->padres()->sync($sync);
    }

    /**
     * Comprueba que el usuario pueda acceder a este estudiante en particular.
     *
     * La validación se hace en el servidor, registro por registro, para que
     * cambiar el identificador en la dirección web no permita consultar datos
     * de alumnos ajenos. Si no tiene acceso, se responde con un error 403.
     */
    private function authorizeAcceso(Request $request, Estudiante $estudiante): void
    {
        abort_unless(Alcance::puedeVerEstudiante($request->user(), $estudiante), 403);
    }
}
