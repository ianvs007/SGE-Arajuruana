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
 * Citaciones (§12).
 *
 * - Emitidas por Docente (solo alumnos de sus cursos), Dirección o Administración.
 * - Registra destinatario, alumno, motivo, emisor, fecha y hora asignadas.
 *   El responsable se ajusta a la convocatoria: no hay reserva de citas ni
 *   negociación de horarios.
 * - Incluye acuerdos, responsable de seguimiento y fecha de revisión.
 * - Si hay incidencia asociada confidencial, el texto dirigido al familiar no
 *   reproduce su detalle (§11/§12).
 */
class CitacionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Citacion::with(['estudiante', 'padre', 'generador', 'seguimientoResponsable']);

        if ($user->esResponsableFamiliar()) {
            $query->where('padre_id', $user->id);
        } elseif ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            // §5: el docente ve citaciones de alumnos de sus cursos o emitidas por él.
            $ids = Alcance::estudiantes($user)->pluck('estudiantes.id');
            $query->where(fn ($q) => $q
                ->whereIn('estudiante_id', $ids)
                ->orWhere('generado_por', $user->id));
        }

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
            'pendientesRevision' => (clone $query)
                ->whereNotNull('fecha_revision')
                ->whereIn('estado', ['atendida', 'en_seguimiento'])
                ->whereDate('fecha_revision', '<=', now()->toDateString())
                ->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('citaciones.create', [
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $this->autorizarAlumnoYDestinatario($request, $data);

        $citacion = Citacion::create($data + ['generado_por' => $request->user()->id]);

        AuditoriaService::registrar('citaciones.crear', $citacion, [
            'estudiante_id' => $citacion->estudiante_id,
            'destinatario_id' => $citacion->padre_id,
            'fecha' => $data['fecha'],
        ]);

        return redirect()->route('citaciones.show', $citacion)->with('success', 'Citación generada.');
    }

    public function show(Request $request, Citacion $citacion): View
    {
        $this->authorizeCitacion($request, $citacion);
        $citacion->load(['estudiante.curso', 'padre', 'generador', 'seguimientoResponsable', 'incidencia.categoria']);

        $user = $request->user();
        // §11: el detalle de una incidencia confidencial es solo para Administración.
        $verDetalleIncidencia = $user->esAdministracion()
            || ($citacion->incidencia && ! $citacion->incidencia->confidencial);

        return view('citaciones.show', [
            'citacion' => $citacion,
            'verDetalleIncidencia' => $verDetalleIncidencia,
            // §13: WhatsApp MANUAL — enlace wa.me con texto precargado; la app
            // no envía ni marca "enviado". Si la incidencia es confidencial, el
            // texto dirigido al familiar no reproduce su detalle (§11).
            'enlaceWhatsApp' => WhatsApp::enlace(
                $citacion->padre?->telefono,
                WhatsApp::textoCitacion($citacion)
            ),
            'puedeEnviarCorreo' => $user->tieneAlcanceInstitucional() || $user->esDocente(),
        ]);
    }

    /**
     * Envío opcional de correo de la citación (§13).
     *
     * - Sin secretos en el código: configuración por MAIL_* en .env.
     * - El fallo NO bloquea: se informa y la citación sigue visible.
     * - §11: si proviene de incidencia confidencial, el correo no reproduce su detalle.
     */
    public function enviarCorreo(Request $request, Citacion $citacion): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCitacion($request, $citacion);
        // Solo emisores institucionales/docentes; el responsable no reenvía a sí mismo.
        abort_unless($user->tieneAlcanceInstitucional() || $user->esDocente(), 403);

        if (! $citacion->padre?->email) {
            return back()->with('error', 'El responsable no tiene correo registrado.');
        }

        try {
            Mail::to($citacion->padre->email)->send(new CitacionMail($citacion));
        } catch (\Throwable $e) {
            // §13: fallo de correo no bloqueante.
            return back()->with('error', 'No se pudo enviar el correo: '.mb_substr($e->getMessage(), 0, 200)
                .' La citación sigue disponible en el sistema.');
        }

        AuditoriaService::registrar('citaciones.correo', $citacion, [
            'destinatario' => $citacion->padre->email,
        ]);

        return back()->with('success', 'Correo de citación enviado a '.$citacion->padre->email.'.');
    }

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

    public function update(Request $request, Citacion $citacion): RedirectResponse
    {
        $this->authorizeCitacion($request, $citacion);

        $data = $this->validar($request);
        $this->autorizarAlumnoYDestinatario($request, $data);

        $estadoAnterior = $citacion->estado;
        $citacion->update($data);

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
     * Registrar el resultado de la atención (acuerdos y seguimiento, §12).
     * Ruta dedicada para que el seguimiento quede explícito y auditado.
     */
    public function registrarSeguimiento(Request $request, Citacion $citacion): RedirectResponse
    {
        $this->authorizeCitacion($request, $citacion);

        $data = $request->validate([
            'estado' => ['required', Rule::in(['atendida', 'no_asistio', 'en_seguimiento', 'cerrada'])],
            'acuerdos' => ['nullable', 'string', 'max:2000'],
            'observaciones_seguimiento' => ['nullable', 'string', 'max:2000'],
            'seguimiento_responsable_id' => ['nullable', 'exists:users,id'],
            'fecha_revision' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $citacion->update($data);

        AuditoriaService::registrar('citaciones.seguimiento', $citacion, [
            'estado' => $data['estado'],
            'seguimiento_responsable_id' => $data['seguimiento_responsable_id'] ?? null,
            'fecha_revision' => $data['fecha_revision'] ?? null,
        ]);

        return back()->with('success', 'Seguimiento registrado.');
    }

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

    /** §6: validación por registro — alumno en alcance y destinatario responsable del alumno. */
    private function autorizarAlumnoYDestinatario(Request $request, array $data): void
    {
        $estudiante = Estudiante::findOrFail($data['estudiante_id']);
        abort_unless(Alcance::puedeVerEstudiante($request->user(), $estudiante), 403);
        abort_unless($estudiante->esRepresentadoPor(User::find($data['padre_id'])), 422);

        // La incidencia asociada debe ser del mismo alumno (§12).
        if (! empty($data['incidencia_id'])) {
            $incidencia = Incidencia::findOrFail($data['incidencia_id']);
            abort_unless((int) $incidencia->estudiante_id === (int) $estudiante->id, 422);
        }
    }

    /** Incidencias seleccionables: solo Administración ve las confidenciales (§11). */
    private function incidenciasSeleccionables(User $user, ?Citacion $citacion = null)
    {
        $query = Incidencia::with('estudiante')
            ->whereNotIn('estado_seguimiento', ['cerrada'])
            ->latest('fecha');

        if (! $user->esAdministracion()) {
            $query->where('confidencial', false);
        }

        // Mantener visible la incidencia ya asociada aunque esté cerrada.
        if ($citacion?->incidencia_id) {
            $asociada = Incidencia::find($citacion->incidencia_id);

            return $query->get()->push($asociada)->unique('id')->values();
        }

        return $query->get();
    }

    /** Usuarios disponibles como responsable de seguimiento. */
    private function usuariosSeguimiento(User $user)
    {
        // Un docente no delega seguimiento en cuentas que no puede gestionar (§5).
        return User::where('activo', true)
            ->when(! $user->tieneAlcanceInstitucional(), fn ($q) => $q->whereKey($user->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function authorizeCitacion(Request $request, Citacion $citacion): void
    {
        $user = $request->user();

        if ($user->esResponsableFamiliar()) {
            abort_unless($citacion->padre_id === $user->id, 403);

            return;
        }

        if ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            // Solo citaciones propias o de alumnos de sus cursos.
            abort_unless(
                $citacion->generado_por === $user->id
                    || Alcance::puedeVerEstudiante($user, $citacion->estudiante),
                403
            );

            return;
        }

        abort_unless($user->tieneAlcanceInstitucional(), 403);
    }
}
