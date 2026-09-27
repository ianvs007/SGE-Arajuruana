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

class PagoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Pago::with(['cargo.estudiante', 'padre', 'confirmador']);

        if ($user->esResponsableFamiliar()) {
            $query->where('padre_id', $user->id);
        }

        $pagos = $query->latest()->paginate(15);

        return view('pagos.index', compact('pagos'));
    }

    public function create(Request $request, CargoCuenta $cuenta): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->esResponsableFamiliar() && $cuenta->padre_id !== $user->id) {
            abort(403);
        }

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

    public function store(Request $request, CargoCuenta $cuenta): RedirectResponse
    {
        $user = $request->user();
        if ($user->esResponsableFamiliar() && $cuenta->padre_id !== $user->id) {
            abort(403);
        }

        $pendiente = $cuenta->montoPendiente();
        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:'.$pendiente],
            'comprobante_nota' => ['nullable', 'string', 'max:255'],
        ]);

        $whatsapp = Configuracion::getValor('whatsapp_pagos', '59170000000');
        $referencia = Pago::generarReferencia();
        $payload = implode('|', [
            $referencia,
            number_format((float) $data['monto'], 2, '.', ''),
            $cuenta->concepto,
            $user->name,
        ]);

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

    public function show(Request $request, Pago $pago): View
    {
        $user = $request->user();
        if ($user->esResponsableFamiliar() && $pago->padre_id !== $user->id) {
            abort(403);
        }

        $pago->load(['cargo.estudiante', 'padre', 'confirmador']);
        $qrSvg = QrCode::format('svg')->size(220)->generate($pago->qr_payload ?? $pago->referencia);

        return view('pagos.show', compact('pago', 'qrSvg'));
    }

    public function pendientes(): View
    {
        $pagos = Pago::with(['cargo.estudiante', 'padre'])
            ->whereIn('estado', ['pendiente', 'en_revision'])
            ->latest()
            ->paginate(15);

        return view('pagos.pendientes', compact('pagos'));
    }

    public function confirmar(Request $request, Pago $pago): RedirectResponse
    {
        $data = $request->validate([
            'observacion_operador' => ['nullable', 'string'],
        ]);

        if (in_array($pago->estado, ['confirmado', 'rechazado'], true)) {
            return back()->with('error', 'Este pago ya fue procesado.');
        }

        DB::transaction(function () use ($pago, $data, $request) {
            $pago->update([
                'estado' => 'confirmado',
                'confirmado_por' => $request->user()->id,
                'confirmado_en' => now(),
                'observacion_operador' => $data['observacion_operador'] ?? 'Confirmado vía WhatsApp Web / operador.',
            ]);

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

    public function rechazar(Request $request, Pago $pago): RedirectResponse
    {
        $data = $request->validate([
            'observacion_operador' => ['required', 'string'],
        ]);

        if (in_array($pago->estado, ['confirmado', 'rechazado'], true)) {
            return back()->with('error', 'Este pago ya fue procesado.');
        }

        $pago->update([
            'estado' => 'rechazado',
            'confirmado_por' => $request->user()->id,
            'confirmado_en' => now(),
            'observacion_operador' => $data['observacion_operador'],
        ]);

        return back()->with('success', 'Pago rechazado.');
    }
}
