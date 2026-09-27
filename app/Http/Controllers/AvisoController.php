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
 * Avisos institucionales (§13).
 *
 * - Destinatarios específicos: comunidad, responsables, docentes,
 *   administración, un curso (responsables + docentes) o la familia de un
 *   alumno. Al publicar se MATERIALIZAN (trazabilidad de a quién se avisó).
 * - Confirmación de lectura OPCIONAL y NO BLOQUEANTE: leer o confirmar solo
 *   registra; el sistema nunca exige confirmar para seguir usándose.
 * - Correo opcional: se intenta el envío y cada resultado queda registrado por
 *   destinatario; un fallo no bloquea el aviso (§13).
 * - WhatsApp MANUAL: la app genera el enlace wa.me con el texto precargado;
 *   el envío lo hace el usuario desde su teléfono. No hay API ni envío
 *   automático, y no se marca "enviado por WhatsApp".
 */
class AvisoController extends Controller
{
    /** Listado de avisos visibles para el usuario (§6: solo lo que le compete). */
    public function index(Request $request): View
    {
        $user = $request->user();

        $avisos = Aviso::query()
            ->paraUsuario($user)
            ->with('creador')
            ->latest('publicado_en')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        // Lecturas/confirmaciones del usuario en esta página (para el indicador).
        $miEstado = $user->avisosRecibidos()
            ->whereIn('aviso_id', $avisos->pluck('id'))
            ->get()
            ->keyBy('aviso_id');

        // Confirmaciones pendientes (opcionales) para el panel de recordatorio.
        $porConfirmar = $user->avisosPorConfirmar()
            ->with('aviso:id,titulo,publicado_en,confirmar_antes')
            ->latest()
            ->take(5)
            ->get();

        return view('avisos.index', compact('avisos', 'miEstado', 'porConfirmar'));
    }

    public function create(Request $request): View
    {
        return view('avisos.create', [
            'cursos' => $this->cursosSeleccionables(),
            'estudiantes' => $this->estudiantesSeleccionables($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $this->autorizarAlcance($request, $data);

        $aviso = Aviso::create($data + [
            'publicado' => false, // se publica explícitamente (materializa destinatarios)
            'creado_por' => $request->user()->id,
        ]);

        AuditoriaService::registrar('avisos.crear', $aviso, [
            'audiencia' => $aviso->audiencia,
            'curso_id' => $aviso->curso_id,
            'estudiante_id' => $aviso->estudiante_id,
        ]);

        if ($request->boolean('publicar_ahora')) {
            NotificacionService::publicar($aviso, $request->user());

            return redirect()->route('avisos.show', $aviso)
                ->with('success', 'Aviso publicado: '.$aviso->destinatarios()->count().' destinatarios.');
        }

        return redirect()->route('avisos.show', $aviso)
            ->with('success', 'Aviso guardado como borrador. Publícalo cuando esté revisado.');
    }

    /** Detalle: contenido, destinatarios materializados, progreso y compartir (§13). */
    public function show(Request $request, Aviso $aviso): View
    {
        $user = $request->user();
        abort_unless($this->puedeVer($user, $aviso), 403);

        $aviso->load(['creador', 'curso', 'estudiante']);
        $miDestino = $aviso->destinatarioDe($user);

        // Registrar lectura automáticamente (no bloqueante, §13).
        if ($miDestino) {
            NotificacionService::marcarLeido($miDestino);
            $miDestino->refresh();
        }

        $destinatarios = $aviso->destinatarios()->with('usuario')->orderBy('id')->get();

        return view('avisos.show', [
            'aviso' => $aviso,
            'miDestino' => $miDestino,
            'progreso' => $aviso->progresoConfirmacion(),
            'destinatarios' => $destinatarios,
            'enlaceWhatsApp' => NotificacionService::enlaceWhatsApp($aviso),
            'esEmisor' => $user->can('avisos.gestionar'),
        ]);
    }

    /** Confirmación de lectura OPCIONAL del destinatario (§13, no bloqueante). */
    public function confirmar(Request $request, Aviso $aviso): RedirectResponse
    {
        $destino = $aviso->destinatarioDe($request->user());
        abort_unless($destino !== null, 403);

        if (NotificacionService::confirmarLectura($destino)) {
            AuditoriaService::registrar('avisos.confirmar_lectura', $aviso);

            return back()->with('success', 'Confirmación registrada. (Opcional: el sistema se usa igual sin confirmar.)');
        }

        return back()->with('error', 'Este aviso no requiere confirmación de lectura.');
    }

    /** Publica un borrador y materializa destinatarios (§13). */
    public function publicar(Request $request, Aviso $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('avisos.gestionar'), 403);
        abort_if($aviso->publicado, 422, 'El aviso ya está publicado.');

        $nuevos = NotificacionService::publicar($aviso, $request->user());

        return redirect()->route('avisos.show', $aviso)
            ->with('success', "Aviso publicado. Destinatarios: {$aviso->destinatarios()->count()} (nuevos: {$nuevos}).");
    }

    /**
     * Envío de correo opcional (§13): intenta el envío a los destinatarios sin
     * correo enviado. Los fallos se registran y NO bloquean el aviso.
     */
    public function enviarCorreo(Request $request, Aviso $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('avisos.gestionar'), 403);
        abort_unless($aviso->publicado, 422, 'Publique el aviso antes de enviar correos.');

        $resultado = NotificacionService::enviarCorreos($aviso);

        $mensaje = "Correos intentados: {$resultado['intentados']}, enviados: {$resultado['enviados']}, con error: {$resultado['errores']}.";
        if ($resultado['errores'] > 0) {
            // §13: fallo de correo no bloqueante — el aviso sigue disponible.
            return back()->with('error', $mensaje.' El aviso sigue visible en el sistema (el correo es opcional).');
        }

        return back()->with('success', $mensaje);
    }

    public function edit(Request $request, Aviso $aviso): View
    {
        abort_unless($request->user()->can('avisos.gestionar'), 403);

        return view('avisos.edit', [
            'aviso' => $aviso,
            'cursos' => $this->cursosSeleccionables(),
            'estudiantes' => $this->estudiantesSeleccionables($request),
        ]);
    }

    public function update(Request $request, Aviso $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('avisos.gestionar'), 403);

        $data = $this->validar($request);
        $this->autorizarAlcance($request, $data);

        $antes = $aviso->only(['titulo', 'contenido', 'tipo', 'audiencia', 'curso_id', 'estudiante_id']);
        $aviso->update($data);

        AuditoriaService::registrar('avisos.actualizar', $aviso, [
            'antes' => $antes,
            'despues' => $aviso->only(array_keys($antes)),
        ]);

        // Si estaba publicado y cambió el alcance, re-materializa (sin duplicar).
        // Nota: los destinatarios anteriores se conservan (trazabilidad §13).
        if ($aviso->publicado) {
            NotificacionService::publicar($aviso);
        }

        return redirect()->route('avisos.show', $aviso)->with('success', 'Aviso actualizado.');
    }

    // ------------------------------------------------------------------

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'contenido' => ['required', 'string', 'max:20000'],
            'tipo' => ['required', Rule::in(array_keys(Aviso::TIPOS))],
            'audiencia' => ['required', Rule::in(array_keys(Aviso::AUDIENCIAS))],
            'curso_id' => ['nullable', 'required_if:audiencia,curso', 'exists:cursos,id'],
            'estudiante_id' => ['nullable', 'required_if:audiencia,familia', 'exists:estudiantes,id'],
            // §13: confirmación OPCIONAL; la fecha es sugerida, no obligatoria.
            'requiere_confirmacion' => ['nullable', 'boolean'],
            'confirmar_antes' => ['nullable', 'date'],
        ]);

        // Normalización: el checkbox y los campos condicionales se guardan
        // canónicos (bool real; curso/alumno solo si el alcance corresponde).
        $data['requiere_confirmacion'] = $request->boolean('requiere_confirmacion');
        $data['curso_id'] = ($data['audiencia'] ?? null) === 'curso' ? ($data['curso_id'] ?? null) : null;
        $data['estudiante_id'] = ($data['audiencia'] ?? null) === 'familia' ? ($data['estudiante_id'] ?? null) : null;

        return $data;
    }

    /** §6: validación por registro — el alcance declarado debe ser real. */
    private function autorizarAlcance(Request $request, array $data): void
    {
        $user = $request->user();

        if (($data['audiencia'] ?? null) === 'curso' && ! empty($data['curso_id'])) {
            // Docente: solo cursos asignados. Institucional: cualquier curso.
            if (! $user->tieneAlcanceInstitucional()) {
                abort_unless($user->tieneCursoAsignado((int) $data['curso_id']), 403);
            }
        }

        if (($data['audiencia'] ?? null) === 'familia' && ! empty($data['estudiante_id'])) {
            $estudiante = Estudiante::findOrFail($data['estudiante_id']);
            abort_unless(Alcance::puedeVerEstudiante($user, $estudiante), 403);
        }
    }

    private function puedeVer($user, Aviso $aviso): bool
    {
        if ($user->can('avisos.gestionar')) {
            return true;
        }

        return $aviso->publicado && $aviso->esDestinatario($user);
    }

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
     * Alumnos seleccionables para el alcance `familia` (§6: acotado por alcance
     * del emisor — un docente solo ve alumnos de sus cursos).
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
