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
 * Controlador de los pagos validados del aporte mensual (módulo económico).
 *
 * Este controlador atiende los pagos que ya fueron aceptados por la
 * institución dentro del flujo nuevo del aporte. Sus funciones principales son:
 *
 * - Listado y detalle de pagos: el responsable familiar solo ve sus pagos y los
 *   que se aplicaron a cuotas de sus hijos, mientras que el personal
 *   institucional con el permiso `aporte.cuotas.ver` puede ver todos.
 * - Registro directo en ventanilla: Administración puede registrar un pago que
 *   la familia entrega en persona, sin que exista un aviso previo. Se aplican
 *   las mismas reglas de distribución entre cuotas que al validar un aviso.
 * - Comprobante interno en PDF: tiene un número único de identificación, pero
 *   NO tiene valor fiscal, no lleva CUF y no debe parecerse a una factura.
 * - Código QR de demostración: es claramente simulado; escanearlo no acredita
 *   ningún pago, y por eso se marca como "DEMOSTRACIÓN" en pantalla y en el PDF.
 * - Anulación trazable: al anular un pago se revierten las aplicaciones a las
 *   cuotas, pero el registro se conserva en el histórico.
 *
 * Roles: Administración registra, valida y anula; Dirección y otros perfiles
 * institucionales consultan; el responsable familiar solo consulta lo propio.
 *
 * El flujo antiguo de la Etapa 2 (`PagoController`, con QR y WhatsApp sobre
 * `cargos_cuenta`) se mantiene sin cambios para no perder los datos históricos.
 */
class AportePagoController extends Controller
{
    /**
     * Muestra el listado paginado de pagos del flujo nuevo.
     *
     * Si quien consulta es un responsable familiar, la lista se limita a sus
     * propios pagos y a los que se aplicaron a cuotas de sus representados.
     * El personal institucional ve todos los pagos y puede filtrarlos por estado.
     *
     * @return View Vista `aporte.pagos.index` con los pagos y el filtro aplicado.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Partimos de los pagos que tienen número de comprobante, porque esa es
        // la marca que distingue a los pagos del flujo nuevo de los antiguos.
        $query = Pago::with(['padre', 'aviso', 'aplicaciones.cuota.estudiante'])
            ->whereNotNull('comprobante_numero'); // solo pagos del flujo nuevo

        if ($user->esResponsableFamiliar()) {
            // El responsable ve sus propios pagos y también los que se aplicaron
            // a cuotas de sus representados. Así, si el padre y la madre tienen
            // cuentas separadas, ambos comparten el mismo estado de cuenta del alumno.
            $idsHijos = $user->estudiantes()->pluck('estudiantes.id');
            $query->where(function ($q) use ($user, $idsHijos) {
                $q->where('padre_id', $user->id)
                    ->orWhereHas('aplicaciones', fn ($q2) => $q2->whereIn('estudiante_id', $idsHijos));
            });
        } else {
            // Para el personal institucional exigimos el permiso de ver cuotas y,
            // si se envió un estado en la URL, filtramos por él.
            abort_unless($user->can('aporte.cuotas.ver'), 403);
            if ($estado = $request->query('estado')) {
                $query->where('estado', $estado);
            }
        }

        // Ordenamos del pago validado más reciente al más antiguo y conservamos
        // los filtros en los enlaces de la paginación.
        $pagos = $query->latest('validado_en')->paginate(15)->withQueryString();

        return view('aporte.pagos.index', [
            'pagos' => $pagos,
            'estadoFiltro' => $request->query('estado'),
        ]);
    }

    /**
     * Muestra el formulario para registrar un pago directo en ventanilla.
     *
     * Lo usa Administración cuando la familia paga en persona. Se cargan las
     * cuotas con saldo de la gestión actual, agrupadas por alumno, y la lista
     * de responsables familiares que pueden figurar como pagadores.
     *
     * @return View Vista `aporte.pagos.create`.
     */
    public function create(Request $request): View
    {
        // Solo quien gestiona avisos de pago (Administración) puede registrar pagos directos.
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);

        $gestion = Gestion::actual();

        // Buscamos las cuotas pendientes o pagadas parcialmente de la gestión
        // actual y las agrupamos por alumno. No hace falta agruparlas por
        // responsable: Administración elige al alumno y ve directamente sus cuotas con saldo.
        $cuotasPorAlumno = CuotaAporte::with('estudiante')
            ->when($gestion, fn ($q) => $q->where('gestion_id', $gestion->id))
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->orderBy('anio')->orderBy('mes')
            ->get()
            ->groupBy('estudiante_id');

        // Lista de usuarios con rol de responsable familiar, para indicar quién paga.
        $responsables = User::role(User::ROL_RESPONSABLE)->orderBy('name')->get();

        return view('aporte.pagos.create', [
            'gestion' => $gestion,
            'cuotasPorAlumno' => $cuotasPorAlumno,
            'responsables' => $responsables,
        ]);
    }

    /**
     * Registra un pago directo y lo distribuye entre las cuotas elegidas.
     *
     * Primero se validan los datos del formulario y luego se delega todo el
     * trabajo a `AporteService::registrarPagoDirecto()`, que aplica las mismas
     * reglas que se usan al validar un aviso de pago (suma exacta, saldos, etc.)
     * dentro de una transacción.
     *
     * @return RedirectResponse Redirige al detalle del pago o regresa con errores.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);

        // Validamos el responsable, el monto recibido y cada fila de la
        // distribución (cuota y monto aplicado). El último arreglo traduce los
        // nombres de los campos para que los mensajes de error se lean en español.
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

        // El servicio se encarga de crear el pago, emitir el comprobante y
        // aplicar los montos a las cuotas. Si alguna regla económica no se
        // cumple, lanza una ValidationException y regresamos al formulario
        // con los datos ingresados y los errores, sin dejar nada a medias.
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

    /**
     * Muestra el detalle de un pago.
     *
     * Incluye cómo se distribuyó entre las cuotas, el comprobante, el código
     * QR de demostración y, si corresponde, los datos de su anulación.
     *
     * @return View Vista `aporte.pagos.show`.
     */
    public function show(Request $request, Pago $pago): View
    {
        // Verificamos que el usuario tenga derecho a ver este pago en particular.
        $this->authorizeVer($request, $pago);

        // Cargamos de una vez todas las relaciones que usa la vista, para evitar
        // muchas consultas pequeñas a la base de datos.
        $pago->load([
            'padre', 'aviso', 'gestion', 'confirmador',
            'aplicaciones.cuota.estudiante', 'anulacion.anulador',
        ]);

        return view('aporte.pagos.show', [
            'pago' => $pago,
            // El QR es solo de demostración: su contenido empieza con
            // "DEMO-NO-VALIDO" para que quede claro que no acredita ningún pago.
            'qrSvg' => QrCode::format('svg')->size(180)->generate(
                'DEMO-NO-VALIDO|'.$pago->comprobante_numero.'|'.Dinero::aDecimal($pago->montoCentavos())
            ),
        ]);
    }

    /**
     * Anula un pago dejando constancia de quién lo hizo y por qué.
     *
     * Solo Administración puede anular. El servicio revierte la distribución,
     * de modo que las cuotas recuperan su saldo y el aviso asociado (si lo hay)
     * vuelve a quedar pendiente; el pago no se borra, queda en el histórico.
     *
     * @return RedirectResponse Redirige al detalle del pago o regresa con errores.
     */
    public function anular(Request $request, Pago $pago): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.pagos.anular'), 403);

        // El motivo es obligatorio para que la anulación quede justificada.
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        // Si el pago no puede anularse (por ejemplo, porque ya estaba anulado),
        // el servicio lanza una ValidationException y mostramos el error.
        try {
            AporteService::anularPago($pago, $request->user(), $data['motivo']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('aporte.pagos.show', $pago)
            ->with('success', 'Pago anulado. Las cuotas recuperaron su saldo y el aviso quedó pendiente de nuevo.');
    }

    /**
     * Genera el comprobante interno del pago en formato PDF.
     *
     * El comprobante tiene una identificación única, pero no tiene valor
     * fiscal. Los montos se calculan en centavos, igual que en la pantalla,
     * para que los totales del PDF coincidan exactamente con los del sistema.
     *
     * @return Response El PDF mostrado directamente en el navegador.
     */
    public function comprobante(Request $request, Pago $pago): Response
    {
        $this->authorizeVer($request, $pago);

        $pago->load(['padre', 'gestion', 'aplicaciones.cuota.estudiante', 'anulacion']);

        // Armamos el PDF a partir de una vista Blade en tamaño A4 y lo enviamos
        // con "stream" para que se abra en el navegador en lugar de descargarse.
        $pdf = Pdf::loadView('aporte.pagos.comprobante', ['pago' => $pago])
            ->setPaper('a4');

        return $pdf->stream('comprobante-'.$pago->comprobante_numero.'.pdf');
    }

    /**
     * Verifica si el usuario actual puede ver un pago concreto.
     *
     * Es una validación por registro: no basta con tener acceso a la ruta,
     * también se revisa que el pago le corresponda. Si no tiene permiso, se
     * corta la petición con un error 403 (o 404 si el pago es del flujo antiguo).
     */
    private function authorizeVer(Request $request, Pago $pago): void
    {
        $user = $request->user();

        // Por estas rutas solo se atienden pagos del flujo nuevo; los antiguos
        // no tienen número de comprobante y se responden como "no encontrado".
        abort_if($pago->comprobante_numero === null, 404);

        if ($user->esResponsableFamiliar()) {
            // El responsable puede ver su propio pago o uno que se haya aplicado a
            // cuotas de sus representados. Esto contempla el caso de padre y madre
            // con cuentas separadas que comparten el estado de cuenta del alumno.
            $esSuyo = $pago->padre_id === $user->id;
            $tocaASusHijos = ! $esSuyo && $pago->aplicaciones()
                ->whereIn('estudiante_id', $user->estudiantes()->pluck('estudiantes.id'))
                ->exists();

            abort_unless($esSuyo || $tocaASusHijos, 403);

            return;
        }

        // El resto de los usuarios necesita el permiso institucional de ver cuotas.
        abort_unless($user->can('aporte.cuotas.ver'), 403);
    }
}
