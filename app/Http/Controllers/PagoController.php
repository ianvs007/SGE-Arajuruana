<?php

namespace App\Http\Controllers;

use App\Models\CargoCuenta;
use App\Models\Configuracion;
use App\Models\Pago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Controlador de pagos de las cuentas (cargos) de los estudiantes.
 *
 * Es el flujo antiguo de pago por QR y WhatsApp sobre cargos de cuenta. Se
 * conserva para consultar los pagos históricos y para que el personal registre
 * pagos de cargos extraordinarios. Las familias ya no generan pagos aquí: el
 * aporte mensual se paga con el QR del colegio ("Informar un pago") o en
 * efectivo en secretaría, dentro del módulo de aporte.
 *
 * Cada responsable familiar solo puede ver sus propios pagos, para que no
 * tenga acceso a la información económica de otras familias.
 */
class PagoController extends Controller
{
    /**
     * Muestra el listado de pagos.
     *
     * Si el usuario es responsable familiar, solo se le muestran sus propios
     * pagos; el personal autorizado ve todos.
     *
     * @return View Vista con el listado paginado de pagos.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Pago::with(['cargo.estudiante', 'padre', 'confirmador']);

        // Un padre solo debe ver sus pagos, nunca los de otras familias.
        if ($user->esResponsableFamiliar()) {
            $query->where('padre_id', $user->id);
        }

        $pagos = $query->latest()->paginate(15);

        return view('pagos.index', compact('pagos'));
    }

    /**
     * Muestra el formulario para pagar un cargo.
     *
     * Se verifica que el cargo pertenezca al responsable familiar y que
     * todavía tenga un saldo pendiente. También se envía el número de
     * WhatsApp configurado para recibir los comprobantes.
     *
     * @param  CargoCuenta  $cuenta  Cargo que se quiere pagar.
     * @return View|RedirectResponse Formulario de pago o redirección si ya está pagado.
     */
    public function create(Request $request, CargoCuenta $cuenta): View|RedirectResponse
    {
        // Las familias ya no generan pagos en este flujo antiguo: pagan con el
        // QR del colegio desde "Informar un pago" o en efectivo en secretaría.
        $user = $request->user();
        if ($user->esResponsableFamiliar()) {
            return $this->redirigirAlFlujoActual();
        }

        // Si el cargo ya no tiene saldo, no tiene sentido mostrar el formulario.
        $pendiente = $cuenta->montoPendiente();
        if ($pendiente <= 0) {
            return redirect()->route('cuentas.show', $cuenta)->with('error', 'Este cargo ya está pagado.');
        }

        return view('pagos.create', [
            'cargo' => $cuenta->load('estudiante'),
            'pendiente' => $pendiente,
            'whatsapp' => Configuracion::getValor('whatsapp_pagos', '59170000000'),
        ]);
    }

    /**
     * Genera un nuevo pago sobre un cargo.
     *
     * El monto no puede superar lo que queda pendiente. Se crea el pago con
     * una referencia única y un texto (payload) que luego se convierte en
     * código QR. El pago queda "en revisión" hasta que un operador lo
     * confirme al recibir el comprobante por WhatsApp.
     *
     * @param  CargoCuenta  $cuenta  Cargo sobre el que se registra el pago.
     * @return RedirectResponse Redirección al detalle del pago generado.
     */
    public function store(Request $request, CargoCuenta $cuenta): RedirectResponse
    {
        // Igual que en el formulario: las familias no generan pagos aquí.
        $user = $request->user();
        if ($user->esResponsableFamiliar()) {
            return $this->redirigirAlFlujoActual();
        }

        // El monto debe ser positivo y no mayor al saldo pendiente, para evitar pagos de más.
        $pendiente = $cuenta->montoPendiente();
        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:'.$pendiente],
            'comprobante_nota' => ['nullable', 'string', 'max:255'],
        ]);

        // Armamos el contenido del QR uniendo con "|" la referencia, el monto con dos
        // decimales, el concepto del cargo y el nombre de quien paga.
        $whatsapp = Configuracion::getValor('whatsapp_pagos', '59170000000');
        $referencia = Pago::generarReferencia();
        $payload = implode('|', [
            $referencia,
            number_format((float) $data['monto'], 2, '.', ''),
            $cuenta->concepto,
            $user->name,
        ]);

        // Creamos el pago en estado "en_revision": todavía no cuenta como pagado
        // hasta que un operador lo confirme.
        $pago = Pago::create([
            'referencia' => $referencia,
            'cargo_id' => $cuenta->id,
            'padre_id' => $cuenta->padre_id,
            'monto' => $data['monto'],
            'estado' => 'en_revision',
            'metodo' => 'qr_whatsapp',
            'qr_payload' => $payload,
            'comprobante_nota' => $data['comprobante_nota'] ?? null,
            'whatsapp_destino' => $whatsapp,
            'solicitado_en' => now(),
        ]);

        return redirect()->route('pagos.show', $pago)->with('success', 'Pago generado. Envíe el comprobante por WhatsApp.');
    }

    /**
     * Muestra el detalle de un pago junto con su código QR.
     *
     * El QR se genera en formato SVG a partir del payload guardado (o de la
     * referencia, si el payload no existe).
     *
     * @return View Vista con el detalle del pago y el QR.
     */
    public function show(Request $request, Pago $pago): View
    {
        // Un padre solo puede abrir el detalle de sus propios pagos.
        $user = $request->user();
        if ($user->esResponsableFamiliar() && $pago->padre_id !== $user->id) {
            abort(403);
        }

        $pago->load(['cargo.estudiante', 'padre', 'confirmador']);
        $qrSvg = QrCode::format('svg')->size(220)->generate($pago->qr_payload ?? $pago->referencia);

        return view('pagos.show', compact('pago', 'qrSvg'));
    }

    /**
     * Muestra los pagos que esperan revisión del operador.
     *
     * Se listan los pagos en estado "pendiente" o "en_revision", es decir,
     * los que todavía no fueron confirmados ni rechazados.
     *
     * @return View Vista con los pagos pendientes de revisión.
     */
    public function pendientes(): View
    {
        $pagos = Pago::with(['cargo.estudiante', 'padre'])
            ->whereIn('estado', ['pendiente', 'en_revision'])
            ->latest()
            ->paginate(15);

        return view('pagos.pendientes', compact('pagos'));
    }

    /**
     * Confirma un pago y actualiza el estado del cargo.
     *
     * Después de confirmar el pago se recalcula cuánto se pagó del cargo: si
     * se cubrió el monto total, el cargo pasa a "pagado"; si solo se cubrió
     * una parte, pasa a "parcial".
     *
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function confirmar(Request $request, Pago $pago): RedirectResponse
    {
        $data = $request->validate([
            'observacion_operador' => ['nullable', 'string'],
        ]);

        // Un pago que ya fue confirmado o rechazado no se vuelve a procesar.
        if (in_array($pago->estado, ['confirmado', 'rechazado'], true)) {
            return back()->with('error', 'Este pago ya fue procesado.');
        }

        // Usamos una transacción para que, si algo falla, no quede el pago confirmado
        // pero la cuenta sin actualizar (o al revés).
        DB::transaction(function () use ($pago, $data, $request) {
            $pago->update([
                'estado' => 'confirmado',
                'confirmado_por' => $request->user()->id,
                'confirmado_en' => now(),
                'observacion_operador' => $data['observacion_operador'] ?? 'Confirmado vía WhatsApp Web / operador.',
            ]);

            // Bloqueamos el cargo mientras lo actualizamos, para que dos confirmaciones
            // simultáneas no calculen el saldo con datos desactualizados.
            $cargo = $pago->cargo()->lockForUpdate()->first();
            $confirmado = $cargo->montoConfirmado();
            if ($confirmado >= (float) $cargo->monto) {
                $cargo->update(['estado' => 'pagado']);
            } elseif ($confirmado > 0) {
                $cargo->update(['estado' => 'parcial']);
            }
        });

        return back()->with('success', 'Pago confirmado y cuenta actualizada.');
    }

    /**
     * Rechaza un pago.
     *
     * Para rechazar es obligatorio escribir una observación, de modo que el
     * responsable familiar sepa por qué no se aceptó su pago. El cargo no se
     * modifica, porque el pago rechazado no cuenta.
     *
     * @return RedirectResponse Regreso a la página anterior con el resultado.
     */
    public function rechazar(Request $request, Pago $pago): RedirectResponse
    {
        $data = $request->validate([
            'observacion_operador' => ['required', 'string'],
        ]);

        // Igual que al confirmar, no se procesa dos veces el mismo pago.
        if (in_array($pago->estado, ['confirmado', 'rechazado'], true)) {
            return back()->with('error', 'Este pago ya fue procesado.');
        }

        // Guardamos quién lo rechazó, cuándo y el motivo.
        $pago->update([
            'estado' => 'rechazado',
            'confirmado_por' => $request->user()->id,
            'confirmado_en' => now(),
            'observacion_operador' => $data['observacion_operador'],
        ]);

        return back()->with('success', 'Pago rechazado.');
    }

    /** Envía a la familia a la pantalla actual para pagar el aporte. */
    private function redirigirAlFlujoActual(): RedirectResponse
    {
        return redirect()->route('aporte.avisos.create')
            ->with('error', 'Este medio de pago ya no se usa. Pague con el QR del colegio e informe su pago aquí, o pague en efectivo en secretaría.');
    }
}
