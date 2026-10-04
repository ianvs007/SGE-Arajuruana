<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\SalidaEstudiante;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Support\Alcance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controlador de salidas autorizadas de estudiantes y su retorno.
 *
 * Este módulo controla cuándo un alumno sale de la unidad educativa antes de
 * la hora (por salud, trámites familiares, etc.). Las reglas que seguimos son:
 * - El Director y Administración autorizan las salidas (permiso "salidas.autorizar").
 * - Solo Administración registra la salida efectiva y el retorno (permiso
 *   "salidas.registrar").
 * - Que una salida esté autorizada no significa que el alumno ya se haya
 *   retirado; por eso son dos pasos distintos.
 * - La identidad de quien retira al alumno se verifica manualmente: se anota
 *   su nombre y documento, que Administración revisa a la vista. No tiene
 *   validez institucional automática, ni se usan listas previas ni
 *   autorizaciones por WhatsApp.
 * - No se crea una nueva salida para un alumno que ya tiene otra abierta sin resolver.
 * - La hora de retorno nunca puede ser anterior a la hora de salida efectiva.
 *
 * La consulta (permiso "salidas.ver") también está disponible para docentes y
 * responsables familiares, pero solo sobre los alumnos de su alcance.
 */
class SalidaEstudianteController extends Controller
{
    /**
     * Muestra el listado de salidas con filtros por estado y fecha.
     *
     * El listado se restringe según el rol: el responsable familiar solo ve
     * las salidas de sus representados y el docente las de sus cursos.
     * También se calcula cuántas salidas siguen abiertas en el día de hoy.
     *
     * @return View Vista con el listado de salidas y el contador del día.
     */
    public function index(Request $request): View
    {
        // Aplicamos los filtros opcionales de estado y fecha.
        $query = SalidaEstudiante::with(['estudiante', 'autorizante', 'registroSalida'])
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->when($request->input('fecha'), fn ($q, $fecha) => $q->whereDate('fecha', $fecha));

        // Restringimos según el rol: el docente solo ve a los alumnos de sus cursos y el
        // responsable familiar solo a sus representados. El personal institucional ve todo.
        $user = $request->user();
        if ($user->esResponsableFamiliar()) {
            $query->whereIn('estudiante_id', Alcance::estudiantes($user)->pluck('estudiantes.id'));
        } elseif ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            $query->whereIn('estudiante_id', Alcance::estudiantes($user)->pluck('estudiantes.id'));
        }

        // "abiertasHoy" cuenta las salidas de hoy que todavía no se resolvieron, como aviso rápido.
        return view('salidas.index', [
            'salidas' => $query->latest('fecha')->latest('id')->paginate(15)->withQueryString(),
            'estados' => SalidaEstudiante::ESTADOS,
            'filtroEstado' => $request->input('estado'),
            'filtroFecha' => $request->input('fecha'),
            'abiertasHoy' => SalidaEstudiante::whereDate('fecha', now()->toDateString())
                ->whereIn('estado', SalidaEstudiante::ESTADOS_ABIERTOS)
                ->count(),
        ]);
    }

    /**
     * Muestra el formulario para autorizar una salida.
     *
     * Lo usan el Director y Administración. Solo se listan los estudiantes
     * activos que están dentro del alcance del usuario.
     *
     * @return View Vista del formulario de autorización.
     */
    public function create(Request $request): View
    {
        return view('salidas.create', [
            'estudiantes' => Alcance::estudiantes($request->user())
                ->where('estado', 'activo')
                ->orderBy('apellidos')
                ->get(),
            'motivos' => SalidaEstudiante::MOTIVOS,
        ]);
    }

    /**
     * Registra la autorización de una salida.
     *
     * Se valida que el alumno esté en el alcance de quien autoriza y que no
     * tenga ya otra salida abierta en la misma fecha. La salida queda en
     * estado "autorizada": el alumno todavía no se fue hasta que
     * Administración registre la salida efectiva.
     *
     * @return RedirectResponse Redirección al detalle de la salida o regreso con error.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', Rule::in(array_keys(SalidaEstudiante::MOTIVOS))],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // Comprobamos registro por registro que el alumno esté dentro del alcance de quien autoriza.
        $estudiante = Estudiante::findOrFail($data['estudiante_id']);
        abort_unless(Alcance::puedeVerEstudiante($user, $estudiante), 403);

        // No permitimos duplicar una salida si el alumno ya tiene otra abierta sin resolver ese día.
        if (SalidaEstudiante::existeAbiertaPara($estudiante->id, $data['fecha'])) {
            return back()->withInput()->with('error',
                'El alumno ya tiene una salida abierta (autorizada o en curso) en esa fecha. Resuélvala antes de crear otra.');
        }

        // Creamos la salida en estado "autorizada", guardando quién la autorizó y cuándo.
        $salida = SalidaEstudiante::create([
            'estudiante_id' => $estudiante->id,
            'fecha' => $data['fecha'],
            'estado' => 'autorizada',
            'motivo' => $data['motivo'],
            'observacion' => $data['observacion'] ?? null,
            'autorizado_por' => $user->id,
            'autorizado_en' => now(),
            'registrado_por' => $user->id,
        ]);

        AuditoriaService::registrar('salidas.autorizar', $salida, [
            'estudiante_id' => $estudiante->id,
            'fecha' => $data['fecha'],
            'motivo' => $data['motivo'],
        ]);

        return redirect()->route('salidas.show', $salida)
            ->with('success', 'Salida autorizada. Aún NO se retiró el alumno: Administración debe registrar la salida efectiva.');
    }

    /**
     * Muestra el detalle de una salida.
     *
     * Antes se verifica que el usuario pueda ver esta salida en particular.
     * También se indica a la vista si el usuario puede registrar la salida
     * efectiva o el retorno, para mostrar u ocultar esos botones.
     *
     * @return View Vista con el detalle de la salida.
     */
    public function show(Request $request, SalidaEstudiante $salida): View
    {
        $this->authorizeVer($request, $salida);

        $salida->load(['estudiante.curso', 'autorizante', 'registroSalida', 'registroRetorno', 'registrador']);

        return view('salidas.show', [
            'salida' => $salida,
            'puedeRegistrar' => $request->user()->can('salidas.registrar'),
        ]);
    }

    /**
     * Registra la salida efectiva del alumno.
     *
     * Es el momento en que el alumno realmente deja la unidad educativa. Se
     * anota la hora, la persona que lo retira, su documento y la verificación
     * manual realizada. Solo Administración puede hacerlo y únicamente sobre
     * una salida que esté autorizada y pendiente.
     *
     * @return RedirectResponse Regreso al detalle con el resultado.
     */
    public function registrarSalida(Request $request, SalidaEstudiante $salida): RedirectResponse
    {
        abort_unless($request->user()->can('salidas.registrar'), 403);

        // Solo se puede marcar como efectiva una salida que esté autorizada y sin procesar.
        if ($salida->estado !== 'autorizada') {
            return back()->with('error', 'Solo se puede registrar la salida efectiva de una salida autorizada pendiente.');
        }

        // Validamos la hora (formato HH:MM) y los datos de quien retira al alumno.
        $data = $request->validate([
            'hora_salida' => ['required', 'date_format:H:i'],
            'responsable_retiro' => ['required', 'string', 'max:150'],
            'documento_responsable' => ['nullable', 'string', 'max:40'],
            'verificacion_retiro' => ['nullable', 'string', 'max:255'],
        ], [], ['responsable_retiro' => 'persona que retira']);

        $salida->update([
            'estado' => 'salida_efectiva',
            // Guardamos la hora en formato H:i:s para poder compararla de forma confiable con la del retorno.
            'hora_salida' => \Illuminate\Support\Carbon::parse($data['hora_salida'])->format('H:i:s'),
            'salida_en' => now(),
            'salida_registrado_por' => $request->user()->id,
            'responsable_retiro' => $data['responsable_retiro'],
            'documento_responsable' => $data['documento_responsable'] ?? null,
            // Se registra la verificación manual hecha por Administración; no implica una validación institucional.
            'verificacion_retiro' => $data['verificacion_retiro'] ?? 'Documento verificado a la vista por Administración',
        ]);

        AuditoriaService::registrar('salidas.salida_efectiva', $salida, [
            'hora_salida' => $data['hora_salida'],
            'responsable_retiro' => $data['responsable_retiro'],
        ]);

        return back()->with('success', 'Salida efectiva registrada. Si el alumno retorna, registre también el retorno.');
    }

    /**
     * Registra el retorno del alumno a la unidad educativa.
     *
     * Solo Administración puede hacerlo y únicamente sobre una salida
     * efectiva que siga en curso. La hora de retorno nunca puede ser anterior
     * a la hora en que salió. Con el retorno, la salida queda resuelta.
     *
     * @return RedirectResponse Regreso al detalle con el resultado.
     */
    public function registrarRetorno(Request $request, SalidaEstudiante $salida): RedirectResponse
    {
        abort_unless($request->user()->can('salidas.registrar'), 403);

        // El retorno solo tiene sentido si el alumno efectivamente salió.
        if ($salida->estado !== 'salida_efectiva') {
            return back()->with('error', 'Solo se registra retorno de una salida efectiva en curso.');
        }

        $data = $request->validate([
            'hora_retorno' => ['required', 'date_format:H:i'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        // Llevamos ambas horas al mismo formato H:i:s para compararlas como texto y así
        // impedir que se registre un retorno anterior a la salida.
        $horaRetorno = \Illuminate\Support\Carbon::parse($data['hora_retorno'])->format('H:i:s');
        $horaSalida = \Illuminate\Support\Str::of((string) $salida->hora_salida)->substr(0, 8);
        if ($horaRetorno < $horaSalida) {
            return back()->withInput()->with('error',
                "La hora de retorno ({$data['hora_retorno']}) no puede ser anterior a la hora de salida ({$horaSalida}).");
        }

        // Marcamos la salida como "retornada"; si no se escribe una nueva observación, se conserva la anterior.
        $salida->update([
            'estado' => 'retornada',
            'hora_retorno' => $horaRetorno,
            'retorno_en' => now(),
            'retorno_registrado_por' => $request->user()->id,
            'observacion' => $data['observacion'] ?? $salida->observacion,
        ]);

        AuditoriaService::registrar('salidas.retorno', $salida, ['hora_retorno' => $data['hora_retorno']]);

        return back()->with('success', 'Retorno registrado. La salida quedó resuelta.');
    }

    /**
     * Cancela una autorización de salida.
     *
     * Solo se puede cancelar mientras el alumno todavía no salió. Es
     * obligatorio indicar el motivo, que se agrega a la observación para que
     * la corrección quede documentada.
     *
     * @return RedirectResponse Regreso al detalle con el resultado.
     */
    public function cancelar(Request $request, SalidaEstudiante $salida): RedirectResponse
    {
        abort_unless($request->user()->can('salidas.autorizar'), 403);

        // Una salida que ya se hizo efectiva no se puede cancelar, solo registrar su retorno.
        if ($salida->estado !== 'autorizada') {
            return back()->with('error', 'Solo se cancela una salida autorizada que aún no tuvo salida efectiva.');
        }

        $request->validate(['motivo_cancelacion' => ['required', 'string', 'max:255']]);

        // Agregamos el motivo al final de la observación existente, separándolo con " | ".
        $salida->update([
            'estado' => 'cancelada',
            'observacion' => trim(($salida->observacion ? $salida->observacion.' | ' : '').'Cancelada: '.$request->input('motivo_cancelacion')),
        ]);

        AuditoriaService::registrar('salidas.cancelar', $salida);

        return back()->with('success', 'Autorización cancelada.');
    }

    /**
     * Verifica si el usuario puede ver una salida en concreto.
     *
     * El personal institucional puede ver todas. El responsable familiar solo
     * las de sus representados y el docente solo las de alumnos de sus
     * cursos. Cualquier otro caso se rechaza con un error 403.
     */
    private function authorizeVer(Request $request, SalidaEstudiante $salida): void
    {
        $user = $request->user();

        // Alcance institucional: puede ver cualquier salida.
        if ($user->tieneAlcanceInstitucional()) {
            return;
        }

        // Responsable familiar: solo si el alumno es uno de sus representados.
        if ($user->esResponsableFamiliar()) {
            abort_unless($user->representaA($salida->estudiante_id), 403);

            return;
        }

        // Docente: solo si el alumno pertenece a alguno de sus cursos.
        if ($user->esDocente()) {
            abort_unless(Alcance::puedeVerEstudiante($user, $salida->estudiante), 403);

            return;
        }

        // Si no encaja en ningún caso anterior, se niega el acceso por defecto.
        abort(403);
    }
}
