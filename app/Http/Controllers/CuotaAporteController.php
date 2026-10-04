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
 * Controlador de las cuotas del aporte mensual y del estado de cuenta (módulo económico).
 *
 * Una cuota representa lo que un alumno debe aportar en un mes determinado de
 * la gestión. Este controlador permite:
 *
 * - Listar las cuotas de forma institucional, con filtros y totales. Lo usan
 *   Administración, Coordinadora y el Director (permiso `aporte.cuotas.ver`).
 * - Generar las cuotas a partir de las inscripciones activas de una gestión y
 *   eximir cuotas puntuales. Esto lo hace Administración o el Director
 *   (permiso `aporte.cuotas.gestionar`). La exención queda registrada y
 *   auditada; nunca se borra una cuota de forma silenciosa.
 * - Consultar el estado de cuenta de un alumno (permiso `aporte.estado_cuenta`).
 *   El personal institucional puede ver el de cualquier alumno, mientras que el
 *   responsable familiar solo ve el de sus representados, lo cual se valida
 *   registro por registro.
 *
 * Es importante recordar que la obligación es del alumno y no de la familia:
 * se genera una cuota por alumno y por mes, de modo que una familia con tres
 * hijos paga tres cuotas (por ejemplo, 3 x Bs 40 = Bs 120 al mes).
 */
class CuotaAporteController extends Controller
{
    /**
     * Muestra el listado institucional de cuotas con filtros y totales.
     *
     * Permite filtrar por gestión, por estado de la cuota y por un texto de
     * búsqueda sobre el nombre, apellido o código del alumno. Además de la
     * lista paginada, calcula los totales emitido, pagado, saldo y vencido
     * sobre todas las cuotas que cumplen los filtros, no solo sobre la página.
     *
     * @return View Vista `aporte.cuotas.index` con las cuotas, los totales,
     *              las gestiones disponibles y los filtros aplicados.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('aporte.cuotas.ver'), 403);

        // Leemos los filtros de la URL. Si no se elige gestión, se usa la actual.
        $gestion = $this->gestionFiltrada($request);
        $estado = $request->query('estado');
        $q = $request->string('q')->toString();

        // Armamos la consulta aplicando cada filtro solo si viene informado.
        // La búsqueda por texto se hace sobre los datos del alumno relacionado.
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

        // Calculamos los totales en centavos sobre la misma consulta filtrada
        // (clonada, para no alterar la que luego se pagina). Trabajar con
        // enteros garantiza que la pantalla, el PDF y el Excel muestren
        // exactamente los mismos totales. Las cuotas exentas no suman porque
        // no representan deuda.
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

        // Ordenamos por periodo (año y mes) y luego por alumno, y paginamos
        // conservando los filtros en los enlaces.
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

    /**
     * Genera las cuotas de las inscripciones activas de una gestión.
     *
     * La operación es idempotente: si se ejecuta varias veces, las cuotas que
     * ya existen no se duplican ni se recalculan, solo se crean las que faltan
     * (por ejemplo, las de alumnos inscritos después de la primera generación).
     * El cálculo en sí lo realiza `AporteService`.
     *
     * @return RedirectResponse Vuelve a la página anterior indicando cuántas
     *                          cuotas se crearon y cuántas ya existían.
     */
    public function generar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.cuotas.gestionar'), 403);

        // La gestión debe existir en la base de datos.
        $data = $request->validate([
            'gestion_id' => ['required', 'exists:gestiones,id'],
        ]);

        // Delegamos la generación al servicio, que devuelve el resumen de
        // cuotas nuevas y existentes.
        $gestion = Gestion::findOrFail($data['gestion_id']);
        $resultado = AporteService::generarCuotasDeGestion($gestion, $request->user());

        // Registramos la acción en la auditoría junto con ese resumen.
        AuditoriaService::registrar('aporte.cuotas.generar', $gestion, $resultado);

        return back()->with(
            'success',
            "Cuotas generadas: {$resultado['generadas']} nuevas, {$resultado['existentes']} ya existían (no se duplican ni se recalculan)."
        );
    }

    /**
     * Exime una cuota, es decir, libera al alumno de pagarla.
     *
     * La cuota no se borra: cambia su estado a "exenta" y se guarda la
     * observación que justifica la decisión, además de registrarse en la
     * auditoría. Así siempre queda constancia de por qué un alumno no pagó
     * determinado mes.
     *
     * @param  CuotaAporte  $cuota  Cuota que se desea eximir.
     * @return RedirectResponse Vuelve a la página anterior con el resultado.
     */
    public function eximir(Request $request, CuotaAporte $cuota): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.cuotas.gestionar'), 403);

        // La observación es obligatoria porque justifica la exención.
        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:500'],
        ]);

        // Reglas de negocio: no tiene sentido eximir una cuota ya pagada, y si
        // tiene pagos aplicados primero hay que anular esos pagos; de lo
        // contrario quedaría dinero registrado sobre una cuota exenta.
        if ($cuota->estado === 'pagada') {
            return back()->with('error', 'Una cuota pagada no puede eximirse.');
        }
        if ($cuota->pagadoCentavos() > 0) {
            return back()->with('error', 'La cuota tiene pagos aplicados; anule el pago antes de eximirla.');
        }

        // Cambio de estado y auditoría dentro de una misma transacción: o se
        // guardan ambos, o no se guarda ninguno.
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
     * Muestra el estado de cuenta del aporte de un alumno.
     *
     * Presenta las cuotas del alumno en la gestión elegida junto con los
     * totales emitido, pagado, saldo y vencido. El responsable familiar solo
     * puede consultar a sus representados: aunque cambie el ID del alumno en la
     * URL, no podrá ver datos ajenos gracias a la validación por registro.
     *
     * @param  Estudiante  $estudiante  Alumno cuyo estado de cuenta se consulta.
     * @return View Vista `aporte.estado_cuenta` con las cuotas y los totales.
     */
    public function estadoCuenta(Request $request, Estudiante $estudiante): View
    {
        // Primero el permiso del módulo y después la validación por registro,
        // que comprueba si este usuario puede ver a este alumno en particular.
        abort_unless($request->user()->can('aporte.estado_cuenta'), 403);
        abort_unless(Alcance::puedeVerEstudiante($request->user(), $estudiante), 403);

        // El cálculo del estado de cuenta está en el servicio para reutilizarlo
        // también en el panel principal y en los reportes.
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

    /**
     * Determina qué gestión usar como filtro.
     *
     * Si en la URL viene el parámetro `gestion`, se busca esa gestión; si no,
     * se usa la gestión actual del sistema.
     *
     * @return Gestion|null La gestión elegida o null si no se encuentra ninguna.
     */
    private function gestionFiltrada(Request $request): ?Gestion
    {
        if ($id = $request->query('gestion')) {
            return Gestion::find($id);
        }

        return Gestion::actual();
    }
}
