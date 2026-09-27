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
 * Salidas autorizadas y retorno (§10).
 *
 * - Director y Administración AUTORIZAN (salidas.autorizar).
 * - Solo Administración registra SALIDA EFECTIVA y RETORNO (salidas.registrar, §20.7).
 * - Autorizar ≠ el alumno ya se retiró.
 * - Verificación manual registrada de quien retira: nombre + documento, validado
 *   a la vista por Administración (decisión confirmada); sin validez institucional
 *   automática, sin listas previas ni autorización por WhatsApp.
 * - No se duplica una salida abierta del mismo alumno sin resolución.
 * - Retorno nunca anterior a la salida efectiva.
 */
class SalidaEstudianteController extends Controller
{
    public function index(Request $request): View
    {
        $query = SalidaEstudiante::with(['estudiante', 'autorizante', 'registroSalida'])
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->when($request->input('fecha'), fn ($q, $fecha) => $q->whereDate('fecha', $fecha));

        // Alcance por rol (§5): docente solo sus cursos; responsable solo sus representados.
        $user = $request->user();
        if ($user->esResponsableFamiliar()) {
            $query->whereIn('estudiante_id', Alcance::estudiantes($user)->pluck('estudiantes.id'));
        } elseif ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            $query->whereIn('estudiante_id', Alcance::estudiantes($user)->pluck('estudiantes.id'));
        }

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

    /** Autorizar una salida (Director y Administración, §10). */
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', Rule::in(array_keys(SalidaEstudiante::MOTIVOS))],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // Validación por registro (§6): el alumno debe estar en el alcance del autorizante.
        $estudiante = Estudiante::findOrFail($data['estudiante_id']);
        abort_unless(Alcance::puedeVerEstudiante($user, $estudiante), 403);

        // §10: no duplicar una salida abierta del mismo alumno sin resolución.
        if (SalidaEstudiante::existeAbiertaPara($estudiante->id, $data['fecha'])) {
            return back()->withInput()->with('error',
                'El alumno ya tiene una salida abierta (autorizada o en curso) en esa fecha. Resuélvala antes de crear otra.');
        }

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
     * Registrar la SALIDA EFECTIVA (§10): hora, persona que retira, documento y
     * verificación manual. Solo Administración (salidas.registrar).
     */
    public function registrarSalida(Request $request, SalidaEstudiante $salida): RedirectResponse
    {
        abort_unless($request->user()->can('salidas.registrar'), 403);

        if ($salida->estado !== 'autorizada') {
            return back()->with('error', 'Solo se puede registrar la salida efectiva de una salida autorizada pendiente.');
        }

        $data = $request->validate([
            'hora_salida' => ['required', 'date_format:H:i'],
            'responsable_retiro' => ['required', 'string', 'max:150'],
            'documento_responsable' => ['nullable', 'string', 'max:40'],
            'verificacion_retiro' => ['nullable', 'string', 'max:255'],
        ], [], ['responsable_retiro' => 'persona que retira']);

        $salida->update([
            'estado' => 'salida_efectiva',
            // Normalizado a H:i:s para comparación confiable con el retorno.
            'hora_salida' => \Illuminate\Support\Carbon::parse($data['hora_salida'])->format('H:i:s'),
            'salida_en' => now(),
            'salida_registrado_por' => $request->user()->id,
            'responsable_retiro' => $data['responsable_retiro'],
            'documento_responsable' => $data['documento_responsable'] ?? null,
            // Verificación manual registrada: no implica validación institucional (§10).
            'verificacion_retiro' => $data['verificacion_retiro'] ?? 'Documento verificado a la vista por Administración',
        ]);

        AuditoriaService::registrar('salidas.salida_efectiva', $salida, [
            'hora_salida' => $data['hora_salida'],
            'responsable_retiro' => $data['responsable_retiro'],
        ]);

        return back()->with('success', 'Salida efectiva registrada. Si el alumno retorna, registre también el retorno.');
    }

    /**
     * Registrar el RETORNO (§10): nunca anterior a la salida efectiva.
     * Solo Administración.
     */
    public function registrarRetorno(Request $request, SalidaEstudiante $salida): RedirectResponse
    {
        abort_unless($request->user()->can('salidas.registrar'), 403);

        if ($salida->estado !== 'salida_efectiva') {
            return back()->with('error', 'Solo se registra retorno de una salida efectiva en curso.');
        }

        $data = $request->validate([
            'hora_retorno' => ['required', 'date_format:H:i'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        // §10: no registrar retorno anterior a la salida.
        $horaRetorno = \Illuminate\Support\Carbon::parse($data['hora_retorno'])->format('H:i:s');
        $horaSalida = \Illuminate\Support\Str::of((string) $salida->hora_salida)->substr(0, 8);
        if ($horaRetorno < $horaSalida) {
            return back()->withInput()->with('error',
                "La hora de retorno ({$data['hora_retorno']}) no puede ser anterior a la hora de salida ({$horaSalida}).");
        }

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

    /** Cancelar una autorización antes de la salida efectiva (corrección documentada, §10). */
    public function cancelar(Request $request, SalidaEstudiante $salida): RedirectResponse
    {
        abort_unless($request->user()->can('salidas.autorizar'), 403);

        if ($salida->estado !== 'autorizada') {
            return back()->with('error', 'Solo se cancela una salida autorizada que aún no tuvo salida efectiva.');
        }

        $request->validate(['motivo_cancelacion' => ['required', 'string', 'max:255']]);

        $salida->update([
            'estado' => 'cancelada',
            'observacion' => trim(($salida->observacion ? $salida->observacion.' | ' : '').'Cancelada: '.$request->input('motivo_cancelacion')),
        ]);

        AuditoriaService::registrar('salidas.cancelar', $salida);

        return back()->with('success', 'Autorización cancelada.');
    }

    /** ¿Puede este usuario ver esta salida en concreto? Validación por registro (§6). */
    private function authorizeVer(Request $request, SalidaEstudiante $salida): void
    {
        $user = $request->user();

        if ($user->tieneAlcanceInstitucional()) {
            return;
        }

        if ($user->esResponsableFamiliar()) {
            abort_unless($user->representaA($salida->estudiante_id), 403);

            return;
        }

        if ($user->esDocente()) {
            abort_unless(Alcance::puedeVerEstudiante($user, $salida->estudiante), 403);

            return;
        }

        abort(403);
    }
}
