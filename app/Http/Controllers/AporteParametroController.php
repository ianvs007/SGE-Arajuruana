<?php

namespace App\Http\Controllers;

use App\Models\AporteParametro;
use App\Models\Gestion;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Parámetros de aporte por gestión (§14).
 *
 * Solo Administración (`aporte.parametros`). Los valores iniciales confirmados
 * (Bs 40, febrero a noviembre, día 10) NO están fijos en el código: son
 * editables aquí. Un cambio de parámetros no recalcula cuotas ya emitidas ni
 * reescribe pagos validados (§14): solo afecta cuotas que aún no se generaron.
 */
class AporteParametroController extends Controller
{
    public function edit(Request $request): View
    {
        $gestiones = Gestion::orderByDesc('anio')->get();
        $gestion = $request->query('gestion')
            ? Gestion::findOrFail($request->query('gestion'))
            : (Gestion::actual() ?? $gestiones->first());

        $parametro = $gestion?->aporteParametro()->first() ?? AporteParametro::deGestion($gestion);

        return view('aporte.parametros', [
            'gestiones' => $gestiones,
            'gestion' => $gestion,
            'parametro' => $parametro,
        ]);
    }

    public function update(Request $request, Gestion $gestion): RedirectResponse
    {
        $data = $request->validate([
            'monto_mensual' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'mes_inicio' => ['required', 'integer', 'between:1,12'],
            'mes_fin' => ['required', 'integer', 'between:1,12', 'gte:mes_inicio'],
            'dia_vencimiento' => ['required', 'integer', 'between:1,31'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'monto_mensual' => 'aporte mensual',
            'dia_vencimiento' => 'día de vencimiento',
        ]);

        $parametro = AporteParametro::updateOrCreate(
            ['gestion_id' => $gestion->id],
            [
                'monto_mensual' => $data['monto_mensual'],
                'mes_inicio' => $data['mes_inicio'],
                'mes_fin' => $data['mes_fin'],
                'dia_vencimiento' => $data['dia_vencimiento'],
                'activo' => $request->boolean('activo', true),
            ]
        );

        // Trazabilidad (§6): cambio de parámetros económicos es acción sensible.
        AuditoriaService::registrar('aporte.parametros.actualizar', $parametro, [
            'gestion_id' => $gestion->id,
            'monto_centavos' => (int) round(((float) $data['monto_mensual']) * 100),
            'rango_meses' => $data['mes_inicio'].'-'.$data['mes_fin'],
            'dia_vencimiento' => $data['dia_vencimiento'],
        ]);

        return redirect()
            ->route('aporte.parametros.edit', ['gestion' => $gestion->id])
            ->with('success', 'Parámetros de aporte guardados. Las cuotas ya emitidas NO se recalculan; el cambio aplica a cuotas que aún no se generaron.');
    }
}
