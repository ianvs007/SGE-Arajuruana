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
 * Controlador de los cursos de cada gestión académica.
 *
 * Forma parte del módulo de configuración y permite crear, editar y
 * desactivar los cursos de una gestión (año escolar). Dentro de cada curso
 * también se administran sus horarios de clases, los docentes asignados y las
 * excepciones del calendario, como feriados o jornadas sin clases.
 *
 * Solo tienen acceso los usuarios con el permiso "configuracion.gestionar"
 * (Administración y Director), porque de esta configuración dependen las
 * inscripciones, el control de asistencia y el módulo económico.
 *
 * Optamos por que la lista de cursos, días y horarios se cargue desde la
 * propia aplicación y no quede fija en el código, para que el colegio pueda
 * adaptarla cada año sin necesidad de reprogramar el sistema.
 */
class CursoController extends Controller
{
    /**
     * Muestra el listado de cursos de una gestión.
     *
     * Por defecto se usa la gestión actual, aunque el usuario puede elegir
     * otra desde un selector. Cada curso incluye la cantidad de alumnos con
     * inscripción activa.
     *
     * @return View Vista con los cursos paginados y la lista de gestiones.
     */
    public function index(Request $request): View
    {
        $gestion = $this->gestionSeleccionada($request);

        // Cursos de la gestión elegida con el conteo de inscritos activos,
        // ordenados primero por el orden configurado y luego por nombre.
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

    /**
     * Muestra el formulario para crear un curso.
     *
     * El curso nuevo viene precargado con la gestión seleccionada, el turno
     * de la mañana y marcado como activo, que son los valores más comunes.
     *
     * @return View Formulario de creación de curso.
     */
    public function create(Request $request): View
    {
        $gestion = $this->gestionSeleccionada($request);

        return view('cursos.create', [
            'curso' => new Curso(['gestion_id' => $gestion->id, 'turno' => 'manana', 'activo' => true]),
            'gestion' => $gestion,
            'niveles' => $this->niveles(),
        ]);
    }

    /**
     * Guarda un curso nuevo.
     *
     * El año lectivo se toma de la gestión a la que pertenece el curso; si por
     * algún motivo no se encuentra, se usa el año en curso. La creación queda
     * registrada en la auditoría.
     *
     * @return RedirectResponse Redirección al listado de cursos de esa gestión.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        $curso = Curso::create($data + ['anio_lectivo' => Gestion::find($data['gestion_id'])?->anio ?? (int) date('Y')]);
        AuditoriaService::registrar('cursos.crear', $curso, ['nombre' => $curso->nombre]);

        return redirect()->route('cursos.index', ['gestion_id' => $curso->gestion_id])
            ->with('success', 'Curso creado.');
    }

    /**
     * Muestra el formulario de edición de un curso.
     *
     * Además de los datos del curso, esta pantalla reúne sus horarios, los
     * docentes asignados (y los disponibles para asignar) y las excepciones
     * del calendario que le afectan.
     *
     * @param  Curso  $curso  Curso que se va a editar.
     * @return View Formulario de edición con toda la información del curso.
     */
    public function edit(Curso $curso): View
    {
        $curso->load(['horarios', 'docentes', 'gestion']);

        // Las excepciones que afectan al curso son las propias del curso y las
        // generales de su gestión (curso_id nulo), como un feriado nacional.
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

    /**
     * Actualiza los datos de un curso y registra el cambio en la auditoría.
     *
     * @param  Curso  $curso  Curso que se actualiza.
     * @return RedirectResponse Redirección al listado de cursos de su gestión.
     */
    public function update(Request $request, Curso $curso): RedirectResponse
    {
        $data = $this->validar($request, $curso->id);

        $curso->update($data);
        AuditoriaService::registrar('cursos.actualizar', $curso, ['nombre' => $curso->nombre]);

        return redirect()->route('cursos.index', ['gestion_id' => $curso->gestion_id])
            ->with('success', 'Curso actualizado.');
    }

    /**
     * Desactiva un curso en lugar de eliminarlo.
     *
     * Nunca borramos el curso de la base de datos, porque tiene asociadas
     * inscripciones, asistencias y otros registros históricos que deben
     * conservarse. Solo se marca como inactivo.
     *
     * @param  Curso  $curso  Curso que se quiere dar de baja.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function destroy(Curso $curso): RedirectResponse
    {
        // Si todavía hay alumnos inscritos de forma activa, no se permite
        // desactivarlo para no dejarlos sin curso.
        if ($curso->inscripciones()->where('estado', 'activa')->exists()) {
            return back()->with('error', 'El curso tiene inscripciones activas. Desactívelo en su lugar.');
        }

        $curso->update(['activo' => false]);

        return back()->with('success', 'Curso desactivado.');
    }

    // --- Horarios del curso ---
    // Cada horario es vigente desde una fecha determinada, de modo que un
    // cambio de horario no altera el significado de las asistencias pasadas.

    /**
     * Agrega un horario de clases al curso.
     *
     * El horario indica el día de la semana, el turno y, de forma opcional, la
     * hora de inicio y fin. Queda activo y vigente desde el momento en que se
     * registra.
     *
     * @param  Curso  $curso  Curso al que se agrega el horario.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function storeHorario(Request $request, Curso $curso): RedirectResponse
    {
        // El día va de 1 (lunes) a 7 (domingo) y, si se indican las horas, la
        // de fin debe ser posterior a la de inicio.
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
     * Desactiva un horario del curso.
     *
     * Desactivar un horario no lo borra: solo cierra su periodo de vigencia
     * con la fecha actual. De esta forma las asistencias registradas antes
     * conservan el significado que tenían en su momento y el cambio solo
     * afecta a las fechas futuras.
     *
     * @param  Curso  $curso  Curso dueño del horario.
     * @param  HorarioCurso  $horario  Horario que se desactiva.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function destroyHorario(Curso $curso, HorarioCurso $horario): RedirectResponse
    {
        // Verificamos que el horario realmente pertenezca al curso de la URL,
        // para que no se pueda desactivar el horario de otro curso.
        abort_unless($horario->curso_id === $curso->id, 404);

        $horario->update(['activo' => false, 'vigente_hasta' => now()]);
        AuditoriaService::registrar('horarios.desactivar', $horario, ['curso_id' => $curso->id]);

        return back()->with('success', 'Horario desactivado: solo afecta fechas futuras; el historial pasado se conserva.');
    }

    // --- Asignación de docentes ---
    // Los docentes asignados determinan el alcance de cada docente: solo
    // trabajan con los alumnos de los cursos que tienen asignados.

    /**
     * Asigna un docente al curso.
     *
     * La asignación se guarda en la tabla intermedia junto con la gestión del
     * curso y el rol de docente de aula.
     *
     * @param  Curso  $curso  Curso al que se asigna el docente.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function asignarDocente(Request $request, Curso $curso): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        // Usamos syncWithoutDetaching para agregar al docente sin quitar a los
        // que ya estaban asignados ni duplicar la asignación si ya existía.
        $curso->docentes()->syncWithoutDetaching([
            $data['user_id'] => ['gestion_id' => $curso->gestion_id, 'rol_docente' => 'docente_aula'],
        ]);
        AuditoriaService::registrar('cursos.asignar_docente', $curso, ['user_id' => $data['user_id']]);

        return back()->with('success', 'Docente asignado al curso.');
    }

    /**
     * Retira a un docente del curso.
     *
     * Solo se elimina la relación entre el docente y el curso; el usuario del
     * docente sigue existiendo en el sistema.
     *
     * @param  Curso  $curso  Curso del que se retira al docente.
     * @param  User  $docente  Docente que se retira.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function quitarDocente(Curso $curso, User $docente): RedirectResponse
    {
        $curso->docentes()->detach($docente->id);

        return back()->with('success', 'Docente retirado del curso.');
    }

    // --- Calendario: jornadas sin clases y otras excepciones ---
    // Estas fechas se toman en cuenta al calcular la asistencia, para no
    // contar como falta un día en que no hubo clases.

    /**
     * Registra una excepción en el calendario (feriado, jornada sin clases, etc.).
     *
     * La excepción puede aplicarse solo a este curso o a toda la gestión,
     * según lo que marque el usuario en el formulario.
     *
     * @param  Curso  $curso  Curso desde el que se registra la excepción.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function storeExcepcion(Request $request, Curso $curso): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', Rule::in(array_keys(CalendarioExcepcion::TIPOS))],
            'motivo' => ['nullable', 'string', 'max:255'],
            'aplica_solo_curso' => ['nullable', 'boolean'],
        ]);

        // Si la casilla "solo este curso" está marcada se guarda el curso; si
        // no, el curso queda nulo y la excepción vale para toda la gestión.
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

    /**
     * Elimina una excepción del calendario.
     *
     * @param  Curso  $curso  Curso desde el que se hizo la solicitud.
     * @param  CalendarioExcepcion  $excepcion  Jornada que se quita del calendario.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function destroyExcepcion(Curso $curso, CalendarioExcepcion $excepcion): RedirectResponse
    {
        $excepcion->delete();

        return back()->with('success', 'Jornada eliminada del calendario.');
    }

    /**
     * Obtiene la gestión con la que se está trabajando.
     *
     * Si en la petición viene un gestion_id se usa esa gestión; de lo
     * contrario se toma la gestión marcada como actual.
     *
     * @return Gestion Gestión seleccionada.
     */
    private function gestionSeleccionada(Request $request): Gestion
    {
        $id = $request->input('gestion_id');
        $gestion = $id ? Gestion::find($id) : Gestion::actual();

        // Sin ninguna gestión no se pueden manejar cursos, así que se corta
        // con un mensaje que indica dónde crearla.
        abort_unless($gestion, 404, 'No hay ninguna gestión configurada. Cree una en Configuración → Gestiones.');

        return $gestion;
    }

    /**
     * Devuelve los niveles educativos que ofrece la unidad educativa.
     *
     * @return array Niveles para el selector del formulario.
     */
    private function niveles(): array
    {
        return ['Inicial' => 'Inicial', 'Primaria' => 'Primaria', 'Secundaria' => 'Secundaria'];
    }

    /**
     * Valida los datos del formulario de cursos.
     *
     * Se usa tanto al crear como al editar. Solo la gestión y el nombre son
     * obligatorios; el resto de los campos son opcionales.
     *
     * @param  int|null  $ignoreId  Id del curso que se edita (actualmente no se usa en las reglas).
     * @return array Datos validados del curso.
     */
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
