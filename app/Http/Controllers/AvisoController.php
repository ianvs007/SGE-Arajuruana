<?php

namespace App\Http\Controllers;

use App\Models\Aviso;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Services\AuditoriaService;
use App\Services\NotificacionService;
use App\Support\Alcance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controlador de los avisos institucionales (comunicados) del módulo de
 * comunicaciones.
 *
 * Desde aquí se redactan, publican, editan y consultan los comunicados que la
 * unidad educativa envía a su comunidad. Lo usan dos grupos de usuarios:
 * - Con el permiso "avisos.gestionar" (Administración, Director, Coordinadora
 *   y Docente) se pueden crear borradores, publicarlos, editarlos y enviar
 *   correos. El docente solo puede dirigir avisos de curso a sus cursos
 *   asignados y avisos de familia a alumnos de esos cursos.
 * - Con el permiso "avisos.ver" (todos los roles, incluido el Responsable
 *   Familiar) cada usuario consulta únicamente los avisos que le corresponden
 *   y puede confirmar su lectura.
 *
 * Decisiones de diseño que tomamos para este módulo:
 * - Los destinatarios pueden ser toda la comunidad, los responsables, los
 *   docentes, la administración, un curso (sus responsables y docentes) o la
 *   familia de un alumno. Al publicar el aviso los destinatarios se guardan
 *   uno por uno en la base de datos, para tener constancia de a quién se avisó
 *   aunque luego cambien los cursos o los usuarios.
 * - La confirmación de lectura es opcional y nunca bloquea: leer o confirmar
 *   solo deja un registro, el sistema no obliga a confirmar para seguir usándose.
 * - El correo también es opcional: se intenta el envío y el resultado queda
 *   registrado por cada destinatario; si falla, el aviso sigue disponible.
 * - WhatsApp se usa de forma manual: el sistema arma el enlace wa.me con el
 *   texto ya cargado y el usuario lo envía desde su teléfono. No usamos ninguna
 *   API ni envío automático, por eso tampoco marcamos el aviso como "enviado
 *   por WhatsApp".
 */
class AvisoController extends Controller
{
    /**
     * Muestra el listado de avisos visibles para el usuario.
     *
     * Cada usuario solo ve lo que le compete: el scope paraUsuario del modelo
     * filtra los avisos según su rol y su audiencia. Además se cargan sus
     * lecturas y confirmaciones para marcar en la lista cuáles ya leyó, y un
     * pequeño panel con las confirmaciones que todavía tiene pendientes.
     *
     * @return View Vista con el listado paginado de avisos.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Avisos que le corresponden al usuario, del más reciente al más antiguo.
        $avisos = Aviso::query()
            ->paraUsuario($user)
            ->with('creador')
            ->latest('publicado_en')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        // Lecturas y confirmaciones del usuario solo para los avisos de esta
        // página; se indexan por aviso_id para consultarlas rápido en la vista.
        $miEstado = $user->avisosRecibidos()
            ->whereIn('aviso_id', $avisos->pluck('id'))
            ->get()
            ->keyBy('aviso_id');

        // Las cinco confirmaciones pendientes más recientes, que se muestran
        // como recordatorio (son opcionales, solo sirven de ayuda).
        $porConfirmar = $user->avisosPorConfirmar()
            ->with('aviso:id,titulo,publicado_en,confirmar_antes')
            ->latest()
            ->take(5)
            ->get();

        return view('avisos.index', compact('avisos', 'miEstado', 'porConfirmar'));
    }

    /**
     * Muestra el formulario para redactar un nuevo aviso.
     *
     * Se envían a la vista los cursos de la gestión actual y los alumnos que
     * el emisor puede elegir, para los avisos dirigidos a un curso o a una
     * familia en particular.
     *
     * @return View Formulario de creación de aviso.
     */
    public function create(Request $request): View
    {
        return view('avisos.create', [
            'cursos' => $this->cursosSeleccionables(),
            'estudiantes' => $this->estudiantesSeleccionables($request),
        ]);
    }

    /**
     * Guarda un aviso nuevo.
     *
     * El aviso se crea siempre como borrador y se registra en la auditoría. Si
     * el usuario marcó "publicar ahora", se publica en el mismo paso; en caso
     * contrario queda como borrador para revisarlo antes de publicarlo.
     *
     * @return RedirectResponse Redirección al detalle del aviso creado.
     */
    public function store(Request $request): RedirectResponse
    {
        // Primero validamos los datos y luego comprobamos que el emisor
        // realmente tenga alcance sobre el curso o el alumno elegido.
        $data = $this->validar($request);
        $this->autorizarAlcance($request, $data);

        // Se guarda como borrador; la publicación es un paso aparte porque es
        // la que genera la lista de destinatarios.
        $aviso = Aviso::create($data + [
            'publicado' => false, // se publica explícitamente (materializa destinatarios)
            'creado_por' => $request->user()->id,
        ]);

        AuditoriaService::registrar('avisos.crear', $aviso, [
            'audiencia' => $aviso->audiencia,
            'curso_id' => $aviso->curso_id,
            'estudiante_id' => $aviso->estudiante_id,
        ]);

        // Si se pidió publicar de inmediato, se materializan los destinatarios
        // y se informa cuántos recibieron el aviso.
        if ($request->boolean('publicar_ahora')) {
            NotificacionService::publicar($aviso, $request->user());

            return redirect()->route('avisos.show', $aviso)
                ->with('success', 'Aviso publicado: '.$aviso->destinatarios()->count().' destinatarios.');
        }

        return redirect()->route('avisos.show', $aviso)
            ->with('success', 'Aviso guardado como borrador. Publícalo cuando esté revisado.');
    }

    /**
     * Muestra el detalle de un aviso.
     *
     * Presenta el contenido, la lista de destinatarios guardados al publicar,
     * el progreso de las confirmaciones y el enlace para compartirlo por
     * WhatsApp. Si quien lo abre es destinatario, su lectura se registra
     * automáticamente.
     *
     * @param  Aviso  $aviso  Aviso que se quiere consultar.
     * @return View Vista con el detalle del aviso.
     */
    public function show(Request $request, Aviso $aviso): View
    {
        // Solo pueden abrirlo los emisores o los destinatarios de un aviso ya
        // publicado; así nadie entra a un aviso ajeno cambiando el id en la URL.
        $user = $request->user();
        abort_unless($this->puedeVer($user, $aviso), 403);

        $aviso->load(['creador', 'curso', 'estudiante']);
        $miDestino = $aviso->destinatarioDe($user);

        // Al abrir el aviso se marca como leído sin pedirle nada al usuario;
        // es solo un registro y no condiciona el uso del sistema.
        if ($miDestino) {
            NotificacionService::marcarLeido($miDestino);
            $miDestino->refresh();
        }

        // Lista completa de destinatarios con su usuario, en el orden en que
        // fueron registrados al publicar.
        $destinatarios = $aviso->destinatarios()->with('usuario')->orderBy('id')->get();

        // El indicador esEmisor permite a la vista mostrar las acciones de
        // gestión (publicar, editar, enviar correo) solo a quien corresponde.
        return view('avisos.show', [
            'aviso' => $aviso,
            'miDestino' => $miDestino,
            'progreso' => $aviso->progresoConfirmacion(),
            'destinatarios' => $destinatarios,
            'enlaceWhatsApp' => NotificacionService::enlaceWhatsApp($aviso),
            'esEmisor' => $user->can('avisos.gestionar'),
        ]);
    }

    /**
     * Registra la confirmación de lectura de un destinatario.
     *
     * La confirmación es opcional y no bloquea nada: solo deja constancia de
     * que la persona leyó el aviso. Únicamente puede confirmar quien figura
     * como destinatario.
     *
     * @param  Aviso  $aviso  Aviso que se confirma.
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function confirmar(Request $request, Aviso $aviso): RedirectResponse
    {
        // Si el usuario no está entre los destinatarios, no tiene nada que confirmar.
        $destino = $aviso->destinatarioDe($request->user());
        abort_unless($destino !== null, 403);

        // El servicio devuelve false cuando el aviso no pide confirmación; en
        // ese caso se avisa al usuario en lugar de registrar algo innecesario.
        if (NotificacionService::confirmarLectura($destino)) {
            AuditoriaService::registrar('avisos.confirmar_lectura', $aviso);

            return back()->with('success', 'Confirmación registrada. (Opcional: el sistema se usa igual sin confirmar.)');
        }

        return back()->with('error', 'Este aviso no requiere confirmación de lectura.');
    }

    /**
     * Publica un aviso que estaba como borrador.
     *
     * Al publicar se calcula y se guarda la lista de destinatarios según la
     * audiencia elegida, de modo que queda constancia de a quién se avisó.
     *
     * @param  Aviso  $aviso  Borrador que se va a publicar.
     * @return RedirectResponse Redirección al detalle con la cantidad de destinatarios.
     */
    public function publicar(Request $request, Aviso $aviso): RedirectResponse
    {
        // Además del middleware de la ruta, volvemos a revisar el permiso y
        // evitamos publicar dos veces el mismo aviso.
        abort_unless($request->user()->can('avisos.gestionar'), 403);
        abort_if($aviso->publicado, 422, 'El aviso ya está publicado.');

        // El servicio devuelve cuántos destinatarios nuevos se agregaron.
        $nuevos = NotificacionService::publicar($aviso, $request->user());

        return redirect()->route('avisos.show', $aviso)
            ->with('success', "Aviso publicado. Destinatarios: {$aviso->destinatarios()->count()} (nuevos: {$nuevos}).");
    }

    /**
     * Envía el aviso por correo electrónico a sus destinatarios.
     *
     * El correo es un canal opcional: se intenta el envío solo a los
     * destinatarios que todavía no lo recibieron. Si algún envío falla, el
     * error queda registrado pero el aviso sigue visible en el sistema.
     *
     * @param  Aviso  $aviso  Aviso publicado que se enviará por correo.
     * @return RedirectResponse Regreso a la página anterior con el resumen del envío.
     */
    public function enviarCorreo(Request $request, Aviso $aviso): RedirectResponse
    {
        // Solo se envían correos de avisos publicados, porque recién entonces
        // existe la lista de destinatarios.
        abort_unless($request->user()->can('avisos.gestionar'), 403);
        abort_unless($aviso->publicado, 422, 'Publique el aviso antes de enviar correos.');

        $resultado = NotificacionService::enviarCorreos($aviso);

        // Armamos un resumen con los correos intentados, enviados y fallidos.
        $mensaje = "Correos intentados: {$resultado['intentados']}, enviados: {$resultado['enviados']}, con error: {$resultado['errores']}.";
        if ($resultado['errores'] > 0) {
            // Un fallo de correo no bloquea nada: se informa el problema, pero
            // el aviso sigue disponible dentro del sistema.
            return back()->with('error', $mensaje.' El aviso sigue visible en el sistema (el correo es opcional).');
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Muestra el formulario para editar un aviso.
     *
     * @param  Aviso  $aviso  Aviso que se va a editar.
     * @return View Formulario de edición con los cursos y alumnos seleccionables.
     */
    public function edit(Request $request, Aviso $aviso): View
    {
        abort_unless($request->user()->can('avisos.gestionar'), 403);

        return view('avisos.edit', [
            'aviso' => $aviso,
            'cursos' => $this->cursosSeleccionables(),
            'estudiantes' => $this->estudiantesSeleccionables($request),
        ]);
    }

    /**
     * Actualiza los datos de un aviso.
     *
     * Se guarda en la auditoría cómo estaba el aviso antes y cómo quedó
     * después del cambio. Si el aviso ya estaba publicado, se vuelve a
     * calcular la lista de destinatarios por si cambió la audiencia.
     *
     * @param  Aviso  $aviso  Aviso que se actualiza.
     * @return RedirectResponse Redirección al detalle del aviso.
     */
    public function update(Request $request, Aviso $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('avisos.gestionar'), 403);

        $data = $this->validar($request);
        $this->autorizarAlcance($request, $data);

        // Guardamos una foto de los campos principales antes del cambio para
        // poder compararla en la auditoría.
        $antes = $aviso->only(['titulo', 'contenido', 'tipo', 'audiencia', 'curso_id', 'estudiante_id']);
        $aviso->update($data);

        AuditoriaService::registrar('avisos.actualizar', $aviso, [
            'antes' => $antes,
            'despues' => $aviso->only(array_keys($antes)),
        ]);

        // Si el aviso ya estaba publicado y cambió el alcance, se vuelven a
        // generar los destinatarios sin duplicar a los que ya estaban. Los
        // destinatarios anteriores no se borran, para no perder la constancia
        // de a quién se le había avisado.
        if ($aviso->publicado) {
            NotificacionService::publicar($aviso);
        }

        return redirect()->route('avisos.show', $aviso)->with('success', 'Aviso actualizado.');
    }

    // ------------------------------------------------------------------
    // Métodos auxiliares
    // ------------------------------------------------------------------

    /**
     * Valida y normaliza los datos del formulario de avisos.
     *
     * Se usa tanto al crear como al editar. El curso es obligatorio solo si
     * la audiencia es un curso, y el alumno solo si la audiencia es una familia.
     *
     * @return array Datos listos para guardar en el modelo Aviso.
     */
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'contenido' => ['required', 'string', 'max:20000'],
            'tipo' => ['required', Rule::in(array_keys(Aviso::TIPOS))],
            'audiencia' => ['required', Rule::in(array_keys(Aviso::AUDIENCIAS))],
            'curso_id' => ['nullable', 'required_if:audiencia,curso', 'exists:cursos,id'],
            'estudiante_id' => ['nullable', 'required_if:audiencia,familia', 'exists:estudiantes,id'],
            // La confirmación de lectura es opcional y la fecha límite es solo
            // una sugerencia, por eso ambos campos pueden quedar vacíos.
            'requiere_confirmacion' => ['nullable', 'boolean'],
            'confirmar_antes' => ['nullable', 'date'],
        ]);

        // Normalizamos los datos: la casilla de confirmación se guarda como un
        // booleano real, y el curso o el alumno solo se conservan si la
        // audiencia lo requiere. Así no quedan datos sobrantes de un alcance
        // que el usuario eligió y luego cambió en el formulario.
        $data['requiere_confirmacion'] = $request->boolean('requiere_confirmacion');
        $data['curso_id'] = ($data['audiencia'] ?? null) === 'curso' ? ($data['curso_id'] ?? null) : null;
        $data['estudiante_id'] = ($data['audiencia'] ?? null) === 'familia' ? ($data['estudiante_id'] ?? null) : null;

        return $data;
    }

    /**
     * Comprueba que el emisor tenga alcance sobre el curso o alumno elegido.
     *
     * No basta con tener el permiso de gestionar avisos: un docente, por
     * ejemplo, no debería poder enviar un aviso a un curso que no es suyo.
     * Por eso se valida registro por registro y se corta con un 403 si el
     * alcance declarado no es real.
     *
     * @param  array  $data  Datos ya validados del aviso.
     */
    private function autorizarAlcance(Request $request, array $data): void
    {
        $user = $request->user();

        if (($data['audiencia'] ?? null) === 'curso' && ! empty($data['curso_id'])) {
            // El personal con alcance institucional puede elegir cualquier
            // curso; el docente solo los cursos que tiene asignados.
            if (! $user->tieneAlcanceInstitucional()) {
                abort_unless($user->tieneCursoAsignado((int) $data['curso_id']), 403);
            }
        }

        // Para un aviso a una familia, el emisor tiene que poder ver al alumno
        // según las reglas de alcance del sistema.
        if (($data['audiencia'] ?? null) === 'familia' && ! empty($data['estudiante_id'])) {
            $estudiante = Estudiante::findOrFail($data['estudiante_id']);
            abort_unless(Alcance::puedeVerEstudiante($user, $estudiante), 403);
        }
    }

    /**
     * Indica si un usuario puede ver el detalle de un aviso.
     *
     * @param  mixed  $user  Usuario autenticado.
     * @param  Aviso  $aviso  Aviso que se quiere consultar.
     * @return bool true si es emisor o si es destinatario de un aviso publicado.
     */
    private function puedeVer($user, Aviso $aviso): bool
    {
        // Quien gestiona avisos puede ver cualquiera, incluidos los borradores.
        if ($user->can('avisos.gestionar')) {
            return true;
        }

        // El resto solo ve avisos publicados en los que figura como destinatario.
        return $aviso->publicado && $aviso->esDestinatario($user);
    }

    /**
     * Devuelve los cursos que se pueden elegir como audiencia de un aviso.
     *
     * Se limitan a los cursos activos de la gestión actual, ordenados como
     * aparecen en el resto del sistema. Si todavía no hay una gestión marcada
     * como actual, se muestran los cursos activos de todas las gestiones.
     *
     * @return \Illuminate\Support\Collection Cursos con los campos necesarios para el selector.
     */
    private function cursosSeleccionables()
    {
        $gestion = Gestion::actual();

        return Curso::query()
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'paralelo', 'turno', 'gestion_id', 'anio_lectivo']);
    }

    /**
     * Devuelve los alumnos que se pueden elegir para un aviso a una familia.
     *
     * La lista depende del alcance del emisor: el personal institucional ve a
     * todos los alumnos activos, mientras que un docente solo ve a los de sus
     * cursos.
     *
     * @return \Illuminate\Support\Collection Alumnos activos ordenados por apellidos y nombres.
     */
    private function estudiantesSeleccionables(Request $request)
    {
        return Alcance::estudiantes($request->user())
            ->where('estado', 'activo')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'apellidos', 'nombres']);
    }
}
