<?php

namespace App\Http\Controllers;

use App\Mail\CitacionMail;
use App\Models\Citacion;
use App\Models\Estudiante;
use App\Models\Incidencia;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Support\Alcance;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controlador de las citaciones a padres de familia (módulo de convivencia).
 *
 * Una citación es la convocatoria formal que hace el colegio al responsable
 * familiar de un alumno para tratar un tema concreto. Este controlador permite
 * listarlas, crearlas, editarlas, registrar el resultado de la reunión
 * (acuerdos y seguimiento) y, de forma opcional, enviarlas por correo.
 *
 * - Las emiten el Docente (solo para alumnos de sus cursos), la Dirección, la
 *   Coordinadora o Administración.
 * - Se registra el destinatario, el alumno, el motivo, quién la emite y la
 *   fecha y hora asignadas. El responsable se ajusta a la convocatoria: el
 *   sistema no maneja reserva de citas ni negociación de horarios.
 * - Incluye los acuerdos alcanzados, un responsable de seguimiento y una
 *   fecha de revisión para no perder de vista los compromisos.
 * - Si la citación está asociada a una incidencia confidencial, el texto que
 *   recibe la familia (WhatsApp o correo) no reproduce el detalle de esa incidencia.
 *
 * Roles: con `citaciones.gestionar` trabajan Administración, Director,
 * Coordinadora y Docente; el responsable familiar, con `citaciones.ver`, solo
 * consulta las citaciones que van dirigidas a él.
 */
class CitacionController extends Controller
{
    /**
     * Muestra el listado paginado de citaciones según el rol del usuario.
     *
     * El responsable familiar ve solo las suyas, el docente las de sus alumnos
     * o las que él emitió, y el personal institucional ve todas. Se puede
     * filtrar por estado y por citaciones con la fecha de revisión vencida.
     *
     * @return View Vista `citaciones.index` con las citaciones y el contador de revisiones pendientes.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Citacion::with(['estudiante', 'padre', 'generador', 'seguimientoResponsable']);

        // Limitamos lo que cada usuario puede ver según su rol.
        if ($user->esResponsableFamiliar()) {
            // El responsable familiar solo ve las citaciones dirigidas a él.
            $query->where('padre_id', $user->id);
        } elseif ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            // El docente ve las citaciones de los alumnos de sus cursos y
            // también las que emitió él mismo, aunque el alumno ya no esté en
            // sus cursos (por ejemplo, si cambió de paralelo).
            $ids = Alcance::estudiantes($user)->pluck('estudiantes.id');
            $query->where(fn ($q) => $q
                ->whereIn('estudiante_id', $ids)
                ->orWhere('generado_por', $user->id));
        }

        // Aplicamos los filtros opcionales. "Revisión vencida" muestra las
        // citaciones atendidas o en seguimiento cuya fecha de revisión ya llegó
        // o pasó, es decir, las que necesitan que alguien retome el caso.
        $citaciones = $query
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->when($request->boolean('revision_vencida'), fn ($q) => $q
                ->whereNotNull('fecha_revision')
                ->whereIn('estado', ['atendida', 'en_seguimiento'])
                ->whereDate('fecha_revision', '<=', now()->toDateString()))
            ->latest('fecha')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('citaciones.index', [
            'citaciones' => $citaciones,
            'estados' => Citacion::ESTADOS,
            // Contador de revisiones vencidas para mostrar un aviso en pantalla.
            // Clonamos la consulta para no alterar la que ya usamos en el listado.
            'pendientesRevision' => (clone $query)
                ->whereNotNull('fecha_revision')
                ->whereIn('estado', ['atendida', 'en_seguimiento'])
                ->whereDate('fecha_revision', '<=', now()->toDateString())
                ->count(),
        ]);
    }

    /**
     * Muestra el formulario para crear una nueva citación.
     *
     * Solo se ofrecen los alumnos activos que el usuario tiene a su alcance
     * (un docente, solo los de sus cursos), junto con sus responsables, las
     * incidencias que se pueden asociar y los usuarios que pueden hacerse
     * cargo del seguimiento.
     *
     * @return View Vista `citaciones.create`.
     */
    public function create(Request $request): View
    {
        return view('citaciones.create', [
            // Cargamos los responsables de cada alumno para que el formulario
            // pueda ofrecer como destinatario solo a sus representantes.
            'estudiantes' => Alcance::estudiantes($request->user())
                ->with('responsables')
                ->where('estado', 'activo')
                ->orderBy('apellidos')
                ->get(),
            'estados' => Citacion::ESTADOS,
            'incidencias' => $this->incidenciasSeleccionables($request->user()),
            'usuarios' => $this->usuariosSeguimiento($request->user()),
        ]);
    }

    /**
     * Guarda una nueva citación.
     *
     * Se validan los datos, se comprueba que el alumno esté al alcance del
     * usuario y que el destinatario sea realmente su responsable, y luego se
     * crea la citación dejando constancia en la auditoría.
     *
     * @return RedirectResponse Redirige al detalle de la citación creada.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $this->autorizarAlumnoYDestinatario($request, $data);

        // Quien emite la citación es siempre el usuario autenticado; no se toma
        // del formulario para que nadie pueda emitir a nombre de otro.
        $citacion = Citacion::create($data + ['generado_por' => $request->user()->id]);

        // Registramos en la auditoría la creación, con el alumno, el destinatario y la fecha.
        AuditoriaService::registrar('citaciones.crear', $citacion, [
            'estudiante_id' => $citacion->estudiante_id,
            'destinatario_id' => $citacion->padre_id,
            'fecha' => $data['fecha'],
        ]);

        return redirect()->route('citaciones.show', $citacion)->with('success', 'Citación generada.');
    }

    /**
     * Muestra el detalle de una citación.
     *
     * Además de los datos de la citación, prepara el enlace de WhatsApp con el
     * texto precargado y decide si el usuario puede ver el detalle de la
     * incidencia asociada y si puede enviar la citación por correo.
     *
     * @return View Vista `citaciones.show`.
     */
    public function show(Request $request, Citacion $citacion): View
    {
        // Primero verificamos que el usuario tenga derecho a ver esta citación.
        $this->authorizeCitacion($request, $citacion);
        $citacion->load(['estudiante.curso', 'padre', 'generador', 'seguimientoResponsable', 'incidencia.categoria']);

        $user = $request->user();
        // El detalle de una incidencia confidencial solo lo ve Administración;
        // los demás usuarios ven el detalle únicamente si no es confidencial.
        $verDetalleIncidencia = $user->esAdministracion()
            || ($citacion->incidencia && ! $citacion->incidencia->confidencial);

        return view('citaciones.show', [
            'citacion' => $citacion,
            'verDetalleIncidencia' => $verDetalleIncidencia,
            // El envío por WhatsApp es MANUAL: generamos un enlace wa.me con el
            // texto ya escrito y es el usuario quien lo abre y lo envía. El
            // sistema no manda mensajes por su cuenta ni marca nada como
            // "enviado". Si la incidencia es confidencial, el texto para el
            // familiar no reproduce su detalle.
            'enlaceWhatsApp' => WhatsApp::enlace(
                $citacion->padre?->telefono,
                WhatsApp::textoCitacion($citacion)
            ),
            // El botón de correo solo aparece para el personal institucional y los docentes.
            'puedeEnviarCorreo' => $user->tieneAlcanceInstitucional() || $user->esDocente(),
        ]);
    }

    /**
     * Envía, de forma opcional, la citación por correo al responsable familiar.
     *
     * - No guardamos credenciales en el código: el servidor de correo se
     *   configura con las variables MAIL_* del archivo .env.
     * - Si el envío falla, no se bloquea nada: se informa el error y la
     *   citación sigue disponible en el sistema.
     * - Si la citación viene de una incidencia confidencial, el correo no
     *   reproduce el detalle de esa incidencia.
     *
     * @return RedirectResponse Regresa a la página anterior con el resultado del envío.
     */
    public function enviarCorreo(Request $request, Citacion $citacion): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCitacion($request, $citacion);
        // Solo el personal institucional y los docentes pueden enviar el correo;
        // no tiene sentido que el responsable se lo reenvíe a sí mismo.
        abort_unless($user->tieneAlcanceInstitucional() || $user->esDocente(), 403);

        // Sin correo registrado no hay a dónde enviar, así que avisamos al usuario.
        if (! $citacion->padre?->email) {
            return back()->with('error', 'El responsable no tiene correo registrado.');
        }

        try {
            Mail::to($citacion->padre->email)->send(new CitacionMail($citacion));
        } catch (\Throwable $e) {
            // Atrapamos cualquier error del envío para que no rompa la página.
            // Mostramos solo los primeros 200 caracteres del mensaje técnico.
            return back()->with('error', 'No se pudo enviar el correo: '.mb_substr($e->getMessage(), 0, 200)
                .' La citación sigue disponible en el sistema.');
        }

        // Si el correo salió bien, dejamos constancia en la auditoría del destinatario.
        AuditoriaService::registrar('citaciones.correo', $citacion, [
            'destinatario' => $citacion->padre->email,
        ]);

        return back()->with('success', 'Correo de citación enviado a '.$citacion->padre->email.'.');
    }

    /**
     * Muestra el formulario de edición de una citación.
     *
     * Carga los mismos datos de apoyo que el formulario de creación; en el caso
     * de las incidencias, se mantiene visible la que ya estaba asociada.
     *
     * @return View Vista `citaciones.edit`.
     */
    public function edit(Request $request, Citacion $citacion): View
    {
        $this->authorizeCitacion($request, $citacion);

        return view('citaciones.edit', [
            'citacion' => $citacion,
            'estudiantes' => Alcance::estudiantes($request->user())->with('responsables')->orderBy('apellidos')->get(),
            'estados' => Citacion::ESTADOS,
            'incidencias' => $this->incidenciasSeleccionables($request->user(), $citacion),
            'usuarios' => $this->usuariosSeguimiento($request->user()),
        ]);
    }

    /**
     * Actualiza los datos de una citación existente.
     *
     * Se aplican las mismas validaciones que al crearla. Si cambió el estado o
     * la citación tiene acuerdos registrados, la modificación se anota en la
     * auditoría con el estado anterior y el nuevo.
     *
     * @return RedirectResponse Redirige al detalle de la citación.
     */
    public function update(Request $request, Citacion $citacion): RedirectResponse
    {
        $this->authorizeCitacion($request, $citacion);

        $data = $this->validar($request);
        $this->autorizarAlumnoYDestinatario($request, $data);

        // Guardamos el estado antes de actualizar para poder compararlo después.
        $estadoAnterior = $citacion->estado;
        $citacion->update($data);

        // Solo auditamos los cambios relevantes: cambio de estado o existencia
        // de acuerdos, que son los datos que importan para el seguimiento.
        if ($estadoAnterior !== $citacion->estado || $citacion->acuerdos) {
            AuditoriaService::registrar('citaciones.actualizar', $citacion, [
                'estado_antes' => $estadoAnterior,
                'estado_despues' => $citacion->estado,
                'fecha_revision' => $citacion->fecha_revision?->toDateString(),
            ]);
        }

        return redirect()->route('citaciones.show', $citacion)->with('success', 'Citación actualizada.');
    }

    /**
     * Registra el resultado de la reunión: estado, acuerdos y seguimiento.
     *
     * Tiene una ruta propia, separada de la edición general, para que el
     * seguimiento quede como una acción explícita y siempre se registre en la
     * auditoría.
     *
     * @return RedirectResponse Regresa a la página anterior con un mensaje de confirmación.
     */
    public function registrarSeguimiento(Request $request, Citacion $citacion): RedirectResponse
    {
        $this->authorizeCitacion($request, $citacion);

        // Aquí solo se aceptan los estados posteriores a la reunión, y la fecha
        // de revisión no puede quedar en el pasado porque es un compromiso futuro.
        $data = $request->validate([
            'estado' => ['required', Rule::in(['atendida', 'no_asistio', 'en_seguimiento', 'cerrada'])],
            'acuerdos' => ['nullable', 'string', 'max:2000'],
            'observaciones_seguimiento' => ['nullable', 'string', 'max:2000'],
            'seguimiento_responsable_id' => ['nullable', 'exists:users,id'],
            'fecha_revision' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $citacion->update($data);

        // El seguimiento siempre queda auditado, con el estado, el responsable y la fecha de revisión.
        AuditoriaService::registrar('citaciones.seguimiento', $citacion, [
            'estado' => $data['estado'],
            'seguimiento_responsable_id' => $data['seguimiento_responsable_id'] ?? null,
            'fecha_revision' => $data['fecha_revision'] ?? null,
        ]);

        return back()->with('success', 'Seguimiento registrado.');
    }

    /**
     * Valida los datos del formulario de citación (creación y edición).
     *
     * Lo centralizamos en un solo método para que crear y editar usen
     * exactamente las mismas reglas. La hora debe venir en formato 24 horas
     * (HH:MM) y el estado debe ser uno de los definidos en el modelo.
     *
     * @return array Datos validados listos para guardar.
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'padre_id' => ['required', 'exists:users,id'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'motivo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'incidencia_id' => ['nullable', 'exists:incidencias,id'],
            'estado' => ['required', Rule::in(array_keys(Citacion::ESTADOS))],
            'acuerdos' => ['nullable', 'string', 'max:2000'],
            'observaciones_seguimiento' => ['nullable', 'string', 'max:2000'],
            'seguimiento_responsable_id' => ['nullable', 'exists:users,id'],
            'fecha_revision' => ['nullable', 'date'],
        ]);
    }

    /**
     * Verifica que el alumno y el destinatario de la citación sean coherentes.
     *
     * Es una validación por registro: el alumno debe estar al alcance del
     * usuario (por ejemplo, en los cursos del docente) y el destinatario debe
     * ser realmente uno de sus responsables. Si no se cumple, se corta la
     * petición con 403 (sin permiso) o 422 (datos inconsistentes).
     */
    private function autorizarAlumnoYDestinatario(Request $request, array $data): void
    {
        $estudiante = Estudiante::findOrFail($data['estudiante_id']);
        abort_unless(Alcance::puedeVerEstudiante($request->user(), $estudiante), 403);
        // Evitamos citar a una persona que no representa al alumno.
        abort_unless($estudiante->esRepresentadoPor(User::find($data['padre_id'])), 422);

        // Si se asoció una incidencia, debe pertenecer al mismo alumno; no tiene
        // sentido citar por la incidencia de otro estudiante.
        if (! empty($data['incidencia_id'])) {
            $incidencia = Incidencia::findOrFail($data['incidencia_id']);
            abort_unless((int) $incidencia->estudiante_id === (int) $estudiante->id, 422);
        }
    }

    /**
     * Devuelve las incidencias que se pueden asociar a una citación.
     *
     * Solo se ofrecen las incidencias que no están cerradas, y las
     * confidenciales únicamente las ve Administración. Al editar, se agrega
     * también la incidencia ya asociada aunque esté cerrada, para no perderla.
     *
     * @param  Citacion|null  $citacion  Citación que se está editando, si la hay.
     * @return \Illuminate\Support\Collection Colección de incidencias.
     */
    private function incidenciasSeleccionables(User $user, ?Citacion $citacion = null)
    {
        // Incidencias abiertas, de la más reciente a la más antigua.
        $query = Incidencia::with('estudiante')
            ->whereNotIn('estado_seguimiento', ['cerrada'])
            ->latest('fecha');

        // Quien no es Administración no puede ver ni asociar incidencias confidenciales.
        if (! $user->esAdministracion()) {
            $query->where('confidencial', false);
        }

        // Al editar, mantenemos visible la incidencia ya asociada aunque esté
        // cerrada, y quitamos duplicados por si ya venía en la lista.
        if ($citacion?->incidencia_id) {
            $asociada = Incidencia::find($citacion->incidencia_id);

            return $query->get()->push($asociada)->unique('id')->values();
        }

        return $query->get();
    }

    /**
     * Devuelve los usuarios que pueden quedar como responsables del seguimiento.
     *
     * El personal institucional puede elegir a cualquier usuario activo; un
     * usuario sin alcance institucional (como el docente) solo puede asignarse
     * a sí mismo, porque no le corresponde delegar el seguimiento en otras
     * personas que no gestiona.
     *
     * @return \Illuminate\Support\Collection Usuarios con id y nombre.
     */
    private function usuariosSeguimiento(User $user)
    {
        return User::where('activo', true)
            ->when(! $user->tieneAlcanceInstitucional(), fn ($q) => $q->whereKey($user->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Verifica si el usuario actual puede acceder a una citación concreta.
     *
     * No basta con tener acceso a la ruta: se revisa registro por registro que
     * la citación le corresponda según su rol. Si no, se responde con 403.
     */
    private function authorizeCitacion(Request $request, Citacion $citacion): void
    {
        $user = $request->user();

        // El responsable familiar solo accede a las citaciones dirigidas a él.
        if ($user->esResponsableFamiliar()) {
            abort_unless($citacion->padre_id === $user->id, 403);

            return;
        }

        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            // El docente accede a las citaciones que emitió o a las de alumnos de sus cursos.
            abort_unless(
                $citacion->generado_por === $user->id
                    || Alcance::puedeVerEstudiante($user, $citacion->estudiante),
                403
            );

            return;
        }

        // El resto necesita alcance institucional (Administración, Dirección, Coordinadora).
        abort_unless($user->tieneAlcanceInstitucional(), 403);
    }
}
