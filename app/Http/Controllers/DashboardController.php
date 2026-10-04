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
 * Controlador del panel principal (dashboard) que se muestra al iniciar sesión.
 *
 * El panel cambia según el rol del usuario y solo muestra datos útiles y
 * autorizados para cada perfil:
 *
 * - Administración, Director, Coordinadora y Subdirector: resumen
 *   institucional con conteos generales. Si además tienen acceso al módulo
 *   económico, se agregan los avisos de pago pendientes de validación y la
 *   recaudación del aporte de la gestión.
 * - Docente: los datos se limitan a los cursos que tiene asignados.
 * - Responsable familiar: solo ve lo propio y lo de sus representados, como
 *   la deuda de aporte de cada hijo y sus avisos de pago. Recordemos que la
 *   obligación del aporte es del alumno, por eso la deuda se muestra por hijo.
 *
 * El conteo de incidencias confidenciales no se muestra a quien no tiene el
 * permiso `incidencias.confidenciales`, ni siquiera como número agregado,
 * para no revelar indirectamente que existen casos reservados.
 *
 * La ruta `/dashboard` está disponible para cualquier usuario autenticado.
 */
class DashboardController extends Controller
{
    /**
     * Arma los datos del panel según el rol del usuario y devuelve la vista.
     *
     * Primero se cargan los avisos institucionales recibidos, que son comunes a
     * todos los perfiles. Después, según el rol, se calcula un arreglo de
     * estadísticas distinto: uno para la familia, otro para el docente y otro
     * para el personal institucional.
     *
     * @return View Vista `dashboard` con las estadísticas y listados del rol.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Inicializamos todas las variables que usa la vista con valores vacíos,
        // porque cada rol solo llena algunas y la vista espera recibirlas todas.
        $misEstudiantes = collect();
        $misCargos = collect();
        $misCitaciones = collect();
        $misPagos = collect();
        $misSalidasAbiertas = null;
        $deudaPorHijo = collect();   // saldo del aporte por cada representado
        $misAvisos = collect();      // avisos de pago recientes del responsable
        $avisosRecientes = collect(); // avisos institucionales (comunicados) recibidos
        $avisosSinLeer = 0;
        $avisosPorConfirmar = 0;      // confirmaciones de lectura opcionales, no bloquean nada

        /**
         * Avisos institucionales recibidos por el usuario, sea cual sea su rol.
         * Cada aviso se guarda como un registro de destinatario por usuario, por
         * eso los consultamos desde esa relación. Se muestran en todos los paneles.
         * Tomamos los seis más recientes, solo de avisos ya publicados.
         */
        $recibidos = $user->avisosRecibidos()
            ->with('aviso')
            ->whereHas('aviso', fn ($q) => $q->where('publicado', true))
            ->latest()
            ->take(6)
            ->get();
        // Descartamos por seguridad los destinatarios cuyo aviso ya no existe.
        $avisosRecientes = $recibidos->filter(fn ($d) => $d->aviso !== null);
        // Contamos los avisos publicados que el usuario todavía no ha leído y
        // los que esperan su confirmación de lectura.
        $avisosSinLeer = $user->avisosRecibidos()
            ->whereNull('leido_en')
            ->whereHas('aviso', fn ($q) => $q->where('publicado', true))
            ->count();
        $avisosPorConfirmar = $user->avisosPorConfirmar()->count();

        if ($user->esResponsableFamiliar()) {
            // ---------- Responsable familiar: solo lo propio ----------
            // Sus representados (hijos) con el curso en el que están.
            $misEstudiantes = $user->estudiantes()->with('curso')->get();
            $idsHijos = $misEstudiantes->pluck('id');

            // Últimos cinco registros de cada tipo que pertenecen al responsable:
            // cargos y pagos del módulo antiguo, citaciones y avisos de pago.
            $misCargos = CargoCuenta::where('padre_id', $user->id)->latest()->take(5)->get();
            $misCitaciones = Citacion::where('padre_id', $user->id)->latest('fecha')->take(5)->get();
            $misPagos = Pago::where('padre_id', $user->id)->latest()->take(5)->get();
            $misAvisos = AvisoPago::where('padre_id', $user->id)->latest('informado_en')->take(5)->get();

            // Deuda de aporte de cada hijo en la gestión actual, en centavos.
            // Reutilizamos el estado de cuenta del servicio para que el panel
            // muestre exactamente las mismas cifras que la pantalla de detalle.
            // Al final solo dejamos a los hijos que efectivamente deben algo.
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

            // Indicadores de las tarjetas del panel familiar.
            $stats = [
                'mis_hijos' => $misEstudiantes->count(),
                'citaciones_pendientes' => Citacion::where('padre_id', $user->id)->where('estado', 'pendiente')->count(),
                'avisos_pago_pendientes' => AvisoPago::where('padre_id', $user->id)->where('estado', 'pendiente')->count(),
                'saldo_aporte_centavos' => $deudaPorHijo->sum('saldo'),
                'pagos_revision' => Pago::where('padre_id', $user->id)->whereIn('estado', ['pendiente', 'en_revision'])->count(),
                'cargos_pendientes' => CargoCuenta::where('padre_id', $user->id)->whereIn('estado', ['pendiente', 'parcial'])->count(),
            ];
            // Saldo pendiente de los cargos del módulo antiguo mostrados arriba.
            $stats['mi_saldo'] = $misCargos->sum(fn ($c) => $c->montoPendiente());

            // Salidas de sus hijos que todavía están abiertas (sin cerrar el
            // retorno o la autorización), para que el responsable las tenga a la vista.
            $misSalidasAbiertas = SalidaEstudiante::whereIn('estudiante_id', $idsHijos)
                ->whereIn('estado', SalidaEstudiante::ESTADOS_ABIERTOS)
                ->latest('fecha')->take(5)->get();
        } elseif ($user->esDocente() && ! $user->tieneAlcanceInstitucional()) {
            // ---------- Docente: acotado a sus cursos asignados ----------
            // Esta rama solo aplica al docente que no tiene además un rol con
            // alcance institucional. Obtenemos los alumnos de sus cursos
            // mediante la clase Alcance, que centraliza esa regla.
            $idsAlumnos = Alcance::estudiantes($user)->pluck('estudiantes.id');

            // Todos los conteos se filtran por sus alumnos; en las citaciones se
            // incluyen además las que el propio docente generó.
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
            // El docente no ve conteos de incidencias en su panel, porque ese
            // módulo lo administra la institución.
        } else {
            // ---------- Institucional: Administración / Dirección / Coord. / Subdirección ----------
            // Conteos generales de toda la unidad educativa. Las citaciones con
            // revisión vencida son las atendidas o en seguimiento cuya fecha de
            // revisión ya llegó y necesitan volver a revisarse.
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

            // Los datos del aporte solo se muestran a quien tiene acceso al
            // módulo económico; por ejemplo, el Subdirector no los ve.
            if ($user->can('aporte.cuotas.ver')) {
                $stats['avisos_pago_pendientes'] = AvisoPago::where('estado', 'pendiente')->count();

                // Recorremos las cuotas de la gestión actual (sin las exentas,
                // que no generan deuda) y acumulamos en centavos lo recaudado y
                // el saldo de las cuotas que ya pasaron su fecha de vencimiento.
                // Pedimos solo las columnas necesarias para aligerar la consulta.
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

            // El conteo de incidencias abiertas solo aparece para quien gestiona
            // ese módulo, y las confidenciales solo se suman si el usuario tiene
            // permiso para verlas; de lo contrario se excluyen del número.
            if ($user->can('incidencias.gestionar')) {
                $stats['incidencias_abiertas'] = Incidencia::whereIn('estado_seguimiento', ['abierta', 'en_seguimiento'])
                    ->when(! $user->can('incidencias.confidenciales'), fn ($q) => $q->where('confidencial', false))
                    ->count();
            }
        }

        // Enviamos a la vista todas las variables; la plantilla decide qué
        // bloques mostrar según los datos y el rol del usuario.
        return view('dashboard', compact(
            'stats', 'misEstudiantes', 'misCargos', 'misCitaciones', 'misPagos',
            'misSalidasAbiertas', 'deudaPorHijo', 'misAvisos',
            'avisosRecientes', 'avisosSinLeer', 'avisosPorConfirmar'
        ));
    }
}
