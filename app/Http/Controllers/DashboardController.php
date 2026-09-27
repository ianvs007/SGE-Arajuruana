<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\AvisoPago;
use App\Models\CargoCuenta;
use App\Models\Citacion;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Incidencia;
use App\Models\Pago;
use App\Models\SalidaEstudiante;
use App\Models\User;
use App\Services\AporteService;
use App\Support\Alcance;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel por rol (§16): solo datos útiles y autorizados.
 * - Administración/Dirección: resumen institucional (incluye avisos pendientes
 *   de validación y recaudación del aporte, §14).
 * - Docente: acotado a sus cursos asignados.
 * - Responsable familiar: solo lo propio y de sus representados (deuda de
 *   aporte por hijo y sus avisos; §14: la obligación es del alumno).
 * - §11: el conteo de incidencias confidenciales NO se muestra a quien
 *   no tiene incidencias.confidenciales (ni siquiera como número agregado).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $misEstudiantes = collect();
        $misCargos = collect();
        $misCitaciones = collect();
        $misPagos = collect();
        $misSalidasAbiertas = null;
        $deudaPorHijo = collect();   // Etapa 4 (§14): saldo de aporte por representado
        $misAvisos = collect();      // Etapa 4: avisos recientes del responsable
        $avisosRecientes = collect(); // Etapa 5 (§13): avisos institucionales recibidos
        $avisosSinLeer = 0;
        $avisosPorConfirmar = 0;      // opcionales, no bloqueantes (§13)

        /**
         * Etapa 5 (§13): avisos recibidos por el usuario (cualquier rol),
         * materializados como destinatario. Se comparten en todos los paneles.
         */
        $recibidos = $user->avisosRecibidos()
            ->with('aviso')
            ->whereHas('aviso', fn ($q) => $q->where('publicado', true))
            ->latest()
            ->take(6)
            ->get();
        $avisosRecientes = $recibidos->filter(fn ($d) => $d->aviso !== null);
        $avisosSinLeer = $user->avisosRecibidos()
            ->whereNull('leido_en')
            ->whereHas('aviso', fn ($q) => $q->where('publicado', true))
            ->count();
        $avisosPorConfirmar = $user->avisosPorConfirmar()->count();

        if ($user->esResponsableFamiliar()) {
            // ---------- Responsable familiar: solo lo propio ----------
            $misEstudiantes = $user->estudiantes()->with('curso')->get();
            $idsHijos = $misEstudiantes->pluck('id');

            $misCargos = CargoCuenta::where('padre_id', $user->id)->latest()->take(5)->get();
            $misCitaciones = Citacion::where('padre_id', $user->id)->latest('fecha')->take(5)->get();
            $misPagos = Pago::where('padre_id', $user->id)->latest()->take(5)->get();
            $misAvisos = AvisoPago::where('padre_id', $user->id)->latest('informado_en')->take(5)->get();

            // Deuda de aporte por hijo (gestión actual; centavos, §14).
            $deudaPorHijo = $misEstudiantes->map(function ($estudiante) {
                $estado = AporteService::estadoDeCuenta($estudiante, Gestion::actual());

                return [
                    'estudiante_id' => $estudiante->id,
                    'estudiante' => $estudiante,
                    'saldo' => $estado['totales']['saldo'],
                    'vencido' => $estado['totales']['vencido'],
                    'cuotas_vencidas' => $estado['totales']['cuotas_pendientes'],
                ];
            })->filter(fn ($fila) => $fila['saldo'] > 0)->values();

            $stats = [
                'mis_hijos' => $misEstudiantes->count(),
                'citaciones_pendientes' => Citacion::where('padre_id', $user->id)->where('estado', 'pendiente')->count(),
                'avisos_pago_pendientes' => AvisoPago::where('padre_id', $user->id)->where('estado', 'pendiente')->count(),
                'saldo_aporte_centavos' => $deudaPorHijo->sum('saldo'),
                'pagos_revision' => Pago::where('padre_id', $user->id)->whereIn('estado', ['pendiente', 'en_revision'])->count(),
                'cargos_pendientes' => CargoCuenta::where('padre_id', $user->id)->whereIn('estado', ['pendiente', 'parcial'])->count(),
            ];
            $stats['mi_saldo'] = $misCargos->sum(fn ($c) => $c->montoPendiente());

            $misSalidasAbiertas = SalidaEstudiante::whereIn('estudiante_id', $idsHijos)
                ->whereIn('estado', SalidaEstudiante::ESTADOS_ABIERTOS)
                ->latest('fecha')->take(5)->get();
        } elseif ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            // ---------- Docente: acotado a sus cursos asignados ----------
            $idsAlumnos = Alcance::estudiantes($user)->pluck('estudiantes.id');

            $stats = [
                'mis_cursos' => Alcance::cursoIdsDocente($user) ? count(Alcance::cursoIdsDocente($user)) : 0,
                'mis_alumnos' => $idsAlumnos->count(),
                'asistencias_hoy' => Asistencia::whereDate('fecha', today())->whereIn('estudiante_id', $idsAlumnos)->count(),
                'citaciones_pendientes' => Citacion::where('estado', 'pendiente')
                    ->where(fn ($q) => $q->whereIn('estudiante_id', $idsAlumnos)->orWhere('generado_por', $user->id))
                    ->count(),
                'salidas_abiertas' => SalidaEstudiante::whereIn('estado', SalidaEstudiante::ESTADOS_ABIERTOS)
                    ->whereIn('estudiante_id', $idsAlumnos)->count(),
            ];
            // §11: el docente NO ve conteos de incidencias (módulo de Administración).
        } else {
            // ---------- Institucional: Administración / Dirección / Coord. / Subdirección ----------
            $stats = [
                'estudiantes' => Estudiante::where('estado', 'activo')->count(),
                'usuarios' => User::where('activo', true)->count(),
                'asistencias_hoy' => Asistencia::whereDate('fecha', today())->count(),
                'citaciones_pendientes' => Citacion::where('estado', 'pendiente')->count(),
                'citaciones_revision_vencida' => Citacion::whereNotNull('fecha_revision')
                    ->whereIn('estado', ['atendida', 'en_seguimiento'])
                    ->whereDate('fecha_revision', '<=', today()->toDateString())
                    ->count(),
                'salidas_abiertas_hoy' => SalidaEstudiante::whereDate('fecha', today())
                    ->whereIn('estado', SalidaEstudiante::ESTADOS_ABIERTOS)->count(),
                'pagos_revision' => Pago::whereIn('estado', ['pendiente', 'en_revision'])->count(),
                'cargos_pendientes' => CargoCuenta::whereIn('estado', ['pendiente', 'parcial'])->count(),
            ];

            // Etapa 4 (§14): solo quien ve el módulo económico.
            if ($user->can('aporte.cuotas.ver')) {
                $stats['avisos_pago_pendientes'] = AvisoPago::where('estado', 'pendiente')->count();

                $hoy = now()->toDateString();
                $recaudado = 0;
                $vencido = 0;
                $cuotasGestion = CuotaAporte::when(Gestion::actual(), fn ($q) => $q->where('gestion_id', Gestion::actual()->id))
                    ->where('estado', '!=', 'exenta')
                    ->get(['monto', 'saldo', 'estado', 'fecha_vencimiento']);
                foreach ($cuotasGestion as $cuota) {
                    $recaudado += $cuota->pagadoCentavos();
                    if ($cuota->estaVencida($hoy)) {
                        $vencido += $cuota->saldoCentavos();
                    }
                }
                $stats['aporte_recaudado_centavos'] = $recaudado;
                $stats['aporte_vencido_centavos'] = $vencido;
            }

            // §11: conteo de incidencias visible solo con permiso del módulo;
            // las confidenciales solo se cuentan para quien puede verlas.
            if ($user->can('incidencias.gestionar')) {
                $stats['incidencias_abiertas'] = Incidencia::whereIn('estado_seguimiento', ['abierta', 'en_seguimiento'])
                    ->when(! $user->can('incidencias.confidenciales'), fn ($q) => $q->where('confidencial', false))
                    ->count();
            }
        }

        return view('dashboard', compact(
            'stats', 'misEstudiantes', 'misCargos', 'misCitaciones', 'misPagos',
            'misSalidasAbiertas', 'deudaPorHijo', 'misAvisos',
            'avisosRecientes', 'avisosSinLeer', 'avisosPorConfirmar'
        ));
    }
}
