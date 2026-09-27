<?php

namespace App\Http\Controllers;

use App\Models\AvisoPago;
use App\Models\CuotaAporte;
use App\Models\Gestion;
use App\Models\User;
use App\Services\AporteService;
use App\Services\AuditoriaService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Avisos de pago (§14, §20.13).
 *
 * - Responsable familiar (`aporte.avisos.informar`): informa el pago con una
 *   NOTA ESCRITA, sin adjuntar imagen de comprobante. Un aviso pendiente NO
 *   reduce deuda ni acredita fondos ni genera comprobante.
 * - Administración (`aporte.avisos.gestionar`): valida (crea el pago único y lo
 *   distribuye entre cuotas) o rechaza con motivo. Validación transaccional con
 *   anti-doble-proceso en AporteService (§20.14).
 */
class AvisoPagoController extends Controller
{
    /** Listado: familia ve solo sus avisos; Administración ve todos. */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = AvisoPago::with(['padre', 'gestion', 'pago'])
            ->when($user->esResponsableFamiliar(), fn ($q) => $q->where('padre_id', $user->id));

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        $avisos = $query->latest('informado_en')->paginate(15)->withQueryString();

        // Deuda actual de los representados (solo familia): orientación para avisar.
        $deudaHijos = null;
        if ($user->esResponsableFamiliar()) {
            $deudaHijos = CuotaAporte::with('estudiante')
                ->whereIn('estudiante_id', $user->estudiantes()->pluck('estudiantes.id'))
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->when(Gestion::actual(), fn ($q) => $q->where('gestion_id', Gestion::actual()->id))
                ->orderBy('anio')->orderBy('mes')
                ->get();
        }

        return view('aporte.avisos.index', [
            'avisos' => $avisos,
            'deudaHijos' => $deudaHijos,
            'estadoFiltro' => $request->query('estado'),
            'pendientes' => $user->can('aporte.avisos.gestionar')
                ? AvisoPago::where('estado', 'pendiente')->count()
                : 0,
        ]);
    }

    /** Formulario del responsable: monto declarado + nota escrita (§14). */
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('aporte.avisos.informar'), 403);

        $user = $request->user();
        $gestion = Gestion::actual();

        // Cuotas con saldo de sus representados en la gestión actual: guía para
        // que la familia sepa cuánto debe (no reduce deuda por verla).
        $cuotasPendientes = $gestion
            ? CuotaAporte::with('estudiante')
                ->whereIn('estudiante_id', $user->estudiantes()->pluck('estudiantes.id'))
                ->where('gestion_id', $gestion->id)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('anio')->orderBy('mes')->get()
            : collect();

        $totalCentavos = $cuotasPendientes->sum(fn ($c) => $c->saldoCentavos());

        return view('aporte.avisos.create', [
            'cuotasPendientes' => $cuotasPendientes,
            'totalPendiente' => $totalCentavos,
            'gestion' => $gestion,
        ]);
    }

    /** Registra el aviso con nota escrita. Sin adjuntos de archivo (§14). */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.informar'), 403);

        $data = $request->validate([
            'monto_declarado' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ], [], ['monto_declarado' => 'monto pagado']);

        // Defensa en profundidad: aunque el formulario no ofrezca archivos, se
        // rechaza cualquier carga adjunta (el aviso es solo nota escrita, §14).
        if ($request->hasFile('comprobante') || $request->allFiles() !== []) {
            throw ValidationException::withMessages([
                'nota' => 'No se aceptan archivos adjuntos en el aviso: informe el pago con la nota escrita.',
            ]);
        }

        $aviso = AvisoPago::create([
            'referencia' => AvisoPago::generarReferencia(),
            'padre_id' => $request->user()->id,
            'gestion_id' => Gestion::actual()?->id,
            'monto_declarado' => Dinero::aDecimal(Dinero::aCentavos($data['monto_declarado'])),
            'nota' => $data['nota'] ?? null,
            'estado' => 'pendiente',
            'informado_en' => now(),
        ]);

        AuditoriaService::registrar('aporte.aviso.informar', $aviso, [
            'monto_centavos' => $aviso->montoCentavos(),
        ]);

        return redirect()->route('aporte.avisos.show', $aviso)
            ->with('success', 'Aviso registrado ('.$aviso->referencia.'). La deuda NO cambia hasta que Administración valide el pago.');
    }

    /** Detalle: familia ve solo lo suyo; Administración ve todo y puede validar. */
    public function show(Request $request, AvisoPago $aviso): View
    {
        $user = $request->user();
        $this->authorizeVer($user, $aviso);

        $aviso->load(['padre', 'gestion', 'revisor', 'pago.aplicaciones.cuota.estudiante']);

        // Para validar: cuotas con saldo de los representados del avisador,
        // agrupadas por alumno (§14: distribución entre hijos y meses).
        $cuotas = collect();
        if ($user->can('aporte.avisos.gestionar') && $aviso->estaPendiente()) {
            $alumnoIds = User::findOrFail($aviso->padre_id)
                ->estudiantes()->pluck('estudiantes.id');

            $cuotas = CuotaAporte::with('estudiante')
                ->whereIn('estudiante_id', $alumnoIds)
                ->when($aviso->gestion_id, fn ($q) => $q->where('gestion_id', $aviso->gestion_id))
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('anio')->orderBy('mes')
                ->get()
                ->groupBy('estudiante_id');
        }

        return view('aporte.avisos.show', [
            'aviso' => $aviso,
            'cuotasPorAlumno' => $cuotas,
        ]);
    }

    /**
     * Validación manual (§14, §20.13–§20.15): crea el pago único y lo distribuye.
     * Toda la lógica transaccional (locks, guardias, suma exacta) vive en
     * AporteService para que las pruebas y la UI compartan las mismas reglas.
     */
    public function validar(Request $request, AvisoPago $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);
        $this->authorizeVer($request->user(), $aviso);

        $data = $request->validate([
            // Distribución: filas cuota_id + monto (varios hijos y meses, §20.12).
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.cuota_id' => ['required', 'integer', 'exists:cuotas_aporte,id'],
            'aplicaciones.*.monto' => ['required', 'numeric', 'min:0.01'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [], [], [
            'aplicaciones.*.cuota_id' => 'cuota',
            'aplicaciones.*.monto' => 'monto aplicado',
        ]);

        try {
            $pago = AporteService::validarAviso(
                $aviso,
                $data['aplicaciones'],
                $request->user(),
                $data['observacion'] ?? null,
            );
        } catch (ValidationException $e) {
            // Errores de reglas económicas (§20.15): se muestran sin registros parciales.
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('aporte.pagos.show', $pago)
            ->with('success', 'Pago validado y distribuido. Comprobante interno '.$pago->comprobante_numero.' emitido.');
    }

    /** Rechazo con motivo (§14): la deuda permanece intacta (§20.13). */
    public function rechazar(Request $request, AvisoPago $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);
        $this->authorizeVer($request->user(), $aviso);

        $data = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ], [], ['motivo_rechazo' => 'motivo del rechazo']);

        try {
            AporteService::rechazarAviso($aviso, $request->user(), $data['motivo_rechazo']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Aviso rechazado. La deuda del alumno permanece sin cambios.');
    }

    /** La familia puede anular su propio aviso mientras esté pendiente. */
    public function anular(Request $request, AvisoPago $aviso): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('aporte.avisos.informar'), 403);
        abort_unless($aviso->padre_id === $user->id, 403);

        if (! $aviso->estaPendiente()) {
            return back()->with('error', 'Solo puede anular un aviso pendiente.');
        }

        $aviso->update(['estado' => 'anulado']);
        AuditoriaService::registrar('aporte.aviso.anular', $aviso, []);

        return back()->with('success', 'Aviso anulado.');
    }

    /** Validación por registro (§6): la familia solo ve sus avisos. */
    private function authorizeVer(User $user, AvisoPago $aviso): void
    {
        if ($user->esResponsableFamiliar()) {
            abort_unless($aviso->padre_id === $user->id, 403);

            return;
        }

        // Institucionales con permiso de gestión de avisos o de pagos.
        abort_unless(
            $user->can('aporte.avisos.gestionar') || $user->can('aporte.cuotas.ver'),
            403
        );
    }
}
