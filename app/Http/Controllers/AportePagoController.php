<?php

namespace App\Http\Controllers;

use App\Models\CuotaAporte;
use App\Models\Gestion;
use App\Models\Pago;
use App\Models\User;
use App\Services\AporteService;
use App\Support\Dinero;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Pagos validados del flujo nuevo (§14, §15).
 *
 * - Listado y detalle: familia ve solo sus pagos; institución ve todos
 *   (`aporte.cuotas.ver`).
 * - Registro directo en ventanilla (Administración): sin aviso previo, mismas
 *   reglas de distribución transaccional.
 * - Comprobante interno PDF (§15): identificación única, SIN valor fiscal,
 *   sin CUF ni apariencia de factura.
 * - QR claramente SIMULADO (§20.16): escanearlo no acredita pago; se marca
 *   "DEMOSTRACIÓN" en pantalla y en el PDF.
 * - Anulación trazable (§14): revierte aplicaciones y conserva histórico.
 *
 * El flujo viejo de la Etapa 2 (`PagoController`, QR+WhatsApp sobre
 * `cargos_cuenta`) se conserva intacto para datos históricos.
 */
class AportePagoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Pago::with(['padre', 'aviso', 'aplicaciones.cuota.estudiante'])
            ->whereNotNull('comprobante_numero'); // solo pagos del flujo nuevo

        if ($user->esResponsableFamiliar()) {
            // Ve sus propios pagos Y los aplicados a cuotas de sus representados
            // (padre y madre comparten el estado de cuenta del alumno, §14).
            $idsHijos = $user->estudiantes()->pluck('estudiantes.id');
            $query->where(function ($q) use ($user, $idsHijos) {
                $q->where('padre_id', $user->id)
                    ->orWhereHas('aplicaciones', fn ($q2) => $q2->whereIn('estudiante_id', $idsHijos));
            });
        } else {
            abort_unless($user->can('aporte.cuotas.ver'), 403);
            if ($estado = $request->query('estado')) {
                $query->where('estado', $estado);
            }
        }

        $pagos = $query->latest('validado_en')->paginate(15)->withQueryString();

        return view('aporte.pagos.index', [
            'pagos' => $pagos,
            'estadoFiltro' => $request->query('estado'),
        ]);
    }

    /** Formulario de pago directo en ventanilla (Administración, §14). */
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);

        $gestion = Gestion::actual();

        // Alumnos con deuda en la gestión actual, agrupados por responsable no es
        // necesario aquí: Administración elige el alumno y ve sus cuotas con saldo.
        $cuotasPorAlumno = CuotaAporte::with('estudiante')
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->orderBy('anio')->orderBy('mes')
            ->get()
            ->groupBy('estudiante_id');

        $responsables = User::role(User::ROL_RESPONSABLE)->orderBy('name')->get();

        return view('aporte.pagos.create', [
            'gestion' => $gestion,
            'cuotasPorAlumno' => $cuotasPorAlumno,
            'responsables' => $responsables,
        ]);
    }

    /** Registra el pago directo y lo distribuye (§14, mismas reglas que el aviso). */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);

        $data = $request->validate([
            'padre_id' => ['required', 'exists:users,id'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.cuota_id' => ['required', 'integer', 'exists:cuotas_aporte,id'],
            'aplicaciones.*.monto' => ['required', 'numeric', 'min:0.01'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [], [], [
            'padre_id' => 'responsable familiar',
            'monto' => 'monto recibido',
            'aplicaciones.*.cuota_id' => 'cuota',
            'aplicaciones.*.monto' => 'monto aplicado',
        ]);

        try {
            $pago = AporteService::registrarPagoDirecto(
                aplicaciones: $data['aplicaciones'],
                monto: $data['monto'],
                pagadorId: (int) $data['padre_id'],
                gestionId: Gestion::actual()?->id,
                operador: $request->user(),
                observacion: $data['observacion'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('aporte.pagos.show', $pago)
            ->with('success', 'Pago registrado y distribuido. Comprobante interno '.$pago->comprobante_numero.' emitido.');
    }

    /** Detalle del pago: distribución, comprobante, QR simulado, anulación. */
    public function show(Request $request, Pago $pago): View
    {
        $this->authorizeVer($request, $pago);

        $pago->load([
            'padre', 'aviso', 'gestion', 'confirmador',
            'aplicaciones.cuota.estudiante', 'anulacion.anulador',
        ]);

        return view('aporte.pagos.show', [
            'pago' => $pago,
            // QR claramente simulado (§20.16): solo demo, no acredita pago.
            'qrSvg' => QrCode::format('svg')->size(180)->generate(
                'DEMO-NO-VALIDO|'.$pago->comprobante_numero.'|'.Dinero::aDecimal($pago->montoCentavos())
            ),
        ]);
    }

    /** Anulación trazable (§14): solo Administración, revierte la distribución. */
    public function anular(Request $request, Pago $pago): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.pagos.anular'), 403);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        try {
            AporteService::anularPago($pago, $request->user(), $data['motivo']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('aporte.pagos.show', $pago)
            ->with('success', 'Pago anulado. Las cuotas recuperaron su saldo y el aviso quedó pendiente de nuevo.');
    }

    /**
     * Comprobante interno PDF (§15): identificación única, sin valor fiscal.
     * Mismos totales que la pantalla (centavos, §14).
     */
    public function comprobante(Request $request, Pago $pago): Response
    {
        $this->authorizeVer($request, $pago);

        $pago->load(['padre', 'gestion', 'aplicaciones.cuota.estudiante', 'anulacion']);

        $pdf = Pdf::loadView('aporte.pagos.comprobante', ['pago' => $pago])
            ->setPaper('a4');

        return $pdf->stream('comprobante-'.$pago->comprobante_numero.'.pdf');
    }

    private function authorizeVer(Request $request, Pago $pago): void
    {
        $user = $request->user();

        // Solo pagos del flujo nuevo por estas rutas.
        abort_if($pago->comprobante_numero === null, 404);

        if ($user->esResponsableFamiliar()) {
            // Su propio pago o uno aplicado a cuotas de sus representados (§14:
            // padre y madre con cuentas separadas comparten el estado del alumno).
            $esSuyo = $pago->padre_id === $user->id;
            $tocaASusHijos = ! $esSuyo && $pago->aplicaciones()
                ->whereIn('estudiante_id', $user->estudiantes()->pluck('estudiantes.id'))
                ->exists();

            abort_unless($esSuyo || $tocaASusHijos, 403);

            return;
        }

        abort_unless($user->can('aporte.cuotas.ver'), 403);
    }
}
