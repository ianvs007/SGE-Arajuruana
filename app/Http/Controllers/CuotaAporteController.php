<?php

namespace App\Http\Controllers;

use App\Models\AporteParametro;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Services\AporteService;
use App\Services\AuditoriaService;
use App\Support\Alcance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cuotas de aporte y estado de cuenta (§14, §16).
 *
 * - La obligación es del ALUMNO: una cuota por alumno/mes (3 hijos = Bs 120, §20.10).
 * - Administración genera cuotas de las inscripciones activas y puede eximir
 *   (trazable, auditable; sin borrado silencioso).
 * - Estado de cuenta: institucional con `aporte.estado_cuenta`; el responsable
 *   familiar ve SOLO el de sus representados (validación por registro, §6).
 */
class CuotaAporteController extends Controller
{
    /** Listado institucional de cuotas con filtros y totales. */
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);

        $gestion = $this->gestionFiltrada($request);
        $estado = $request->query('estado');
        $q = $request->string('q')->toString();

        $query = CuotaAporte::with(['estudiante', 'inscripcion.curso'])
            ->when($gestion, fn ($q2) => $q2->where('gestion_id', $gestion->id))
            ->when($estado, fn ($q2) => $q2->where('estado', $estado))
            ->when($q, function ($q2) use ($q) {
                $q2->whereHas('estudiante', function ($q3) use ($q) {
                    $q3->where('nombres', 'like', "%{$q}%")
                        ->orWhere('apellidos', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%");
                });
            });

        // Totales EN CENTAVOS sobre la misma consulta filtrada (§14: pantalla,
        // PDF y Excel con totales idénticos — Etapa 5 reutilizará este cálculo).
        $totales = ['emitido' => 0, 'pagado' => 0, 'saldo' => 0, 'vencido' => 0];
        $hoy = now()->toDateString();
        foreach ($query->clone()->get() as $cuota) {
            if ($cuota->estado === 'exenta') {
                continue;
            }
            $totales['emitido'] += $cuota->montoCentavos();
            $totales['pagado'] += $cuota->pagadoCentavos();
            $totales['saldo'] += $cuota->saldoCentavos();
            if ($cuota->estaVencida($hoy)) {
                $totales['vencido'] += $cuota->saldoCentavos();
            }
        }

        $cuotas = $query->orderBy('anio')->orderBy('mes')->orderBy('estudiante_id')
            ->paginate(20)->withQueryString();

        return view('aporte.cuotas.index', [
            'cuotas' => $cuotas,
            'totales' => $totales,
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'gestion' => $gestion,
            'estadoFiltro' => $estado,
            'q' => $q,
        ]);
    }

    /** Genera las cuotas de las inscripciones activas de una gestión (idempotente). */
    public function generar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.cuotas.gestionar'), 403);

        $data = $request->validate([
            'gestion_id' => ['required', 'exists:gestiones,id'],
        ]);

        $gestion = Gestion::findOrFail($data['gestion_id']);
        $resultado = AporteService::generarCuotasDeGestion($gestion, $request->user());

        AuditoriaService::registrar('aporte.cuotas.generar', $gestion, $resultado);

        return back()->with(
            'success',
            "Cuotas generadas: {$resultado['generadas']} nuevas, {$resultado['existentes']} ya existían (no se duplican ni se recalculan)."
        );
    }

    /** Exime una cuota (trazable; la cuota no se borra, §14). */
    public function eximir(Request $request, CuotaAporte $cuota): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.cuotas.gestionar'), 403);

        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:500'],
        ]);

        if ($cuota->estado === 'pagada') {
            return back()->with('error', 'Una cuota pagada no puede eximirse.');
        }
        if ($cuota->pagadoCentavos() > 0) {
            return back()->with('error', 'La cuota tiene pagos aplicados; anule el pago antes de eximirla.');
        }

        DB::transaction(function () use ($cuota, $data, $request) {
            $cuota->update([
                'estado' => 'exenta',
                'observacion' => $data['observacion'],
            ]);
            AuditoriaService::registrar('aporte.cuotas.eximir', $cuota, [
                'estudiante_id' => $cuota->estudiante_id,
                'periodo' => $cuota->etiquetaPeriodo(),
            ]);
        });

        return back()->with('success', 'Cuota eximida. Queda registrada la observación y la auditoría.');
    }

    /**
     * Estado de cuenta por alumno (§14, §16).
     * Responsable familiar: SOLO sus representados — alterar el ID en la URL
     * no permite consultar datos ajenos (§6, validación por registro).
     */
    public function estadoCuenta(Request $request, Estudiante $estudiante): View
    {
        abort_unless($request->user()->can('aporte.estado_cuenta'), 403);
        abort_unless(Alcance::puedeVerEstudiante($request->user(), $estudiante), 403);

        $gestion = $this->gestionFiltrada($request);
        $resultado = AporteService::estadoDeCuenta($estudiante, $gestion);

        return view('aporte.estado_cuenta', [
            'estudiante' => $estudiante,
            'gestion' => $gestion,
            'gestiones' => Gestion::orderByDesc('anio')->get(),
            'cuotas' => $resultado['cuotas'],
            'totales' => $resultado['totales'],
        ]);
    }

    /** Gestión del filtro (query `gestion`) o la actual por defecto. */
    private function gestionFiltrada(Request $request): ?Gestion
    {
        if ($id = $request->query('gestion')) {
            return Gestion::find($id);
        }

        return Gestion::actual();
    }
}
