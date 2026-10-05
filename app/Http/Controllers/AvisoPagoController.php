<?php

namespace App\Http\Controllers;

use App\Models\AvisoPago;
use App\Models\AvisoPagoCuota;
use App\Models\CuotaAporte;
use App\Models\Gestion;
use App\Models\User;
use App\Services\AporteService;
use App\Services\AuditoriaService;
use App\Support\DatosPago;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador de los avisos de pago del aporte mensual (módulo económico).
 *
 * Un aviso de pago es la forma en que la familia le comunica a la institución
 * que ya pagó el aporte con el QR del colegio. Este controlador cubre todo el
 * ciclo de vida de ese aviso:
 *
 * - Responsable familiar (permiso `aporte.avisos.informar`): marca los meses
 *   de sus hijos que está pagando, indica la fecha del pago y sube el
 *   comprobante de su banco. Mientras el aviso esté pendiente NO reduce la
 *   deuda, no acredita fondos y no genera comprobante interno. También puede
 *   anular su propio aviso mientras siga pendiente.
 * - Administración y Coordinadora (permiso `aporte.avisos.gestionar`): revisan
 *   el comprobante, verifican en la plataforma de su banco (fuera del sistema)
 *   que el dinero ingresó y recién entonces validan, con el número de
 *   operación bancaria. Si el dinero no llegó, rechazan indicando el motivo.
 *   La validación es transaccional y está protegida contra el doble
 *   procesamiento dentro de `AporteService`.
 * - El Director tiene todos los permisos y, por tanto, puede hacer ambas cosas,
 *   aunque nunca puede validar un aviso que informó él mismo.
 *
 * Además de los permisos que exigen las rutas, cada aviso se valida registro
 * por registro para que una familia nunca pueda ver avisos ajenos.
 */
class AvisoPagoController extends Controller
{
    /**
     * Muestra el listado paginado de avisos de pago.
     *
     * El responsable familiar solo ve los avisos que él mismo informó, mientras
     * que el personal institucional ve todos. Para la familia se calcula además
     * la deuda actual de sus hijos, y para quien gestiona avisos se cuenta
     * cuántos siguen pendientes de revisión.
     *
     * @return View Vista `aporte.avisos.index` con los avisos, la deuda de los
     *              hijos (solo familia), el filtro de estado y los pendientes.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Si quien consulta es responsable familiar, restringimos la consulta a
        // sus propios avisos; el personal institucional ve todos.
        $query = AvisoPago::with(['padre', 'gestion', 'pago'])
            ->when($user->esResponsableFamiliar(), fn ($q) => $q->where('padre_id', $user->id));

        // Filtro opcional por estado (pendiente, validado, rechazado, anulado).
        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        // Mostramos primero los avisos informados más recientemente y
        // conservamos el filtro en los enlaces de la paginación.
        $avisos = $query->latest('informado_en')->paginate(15)->withQueryString();

        // Solo para la familia: cuotas pendientes o parciales de sus hijos en la
        // gestión actual. Sirve como orientación para saber cuánto debe avisar.
        $deudaHijos = null;
        if ($user->esResponsableFamiliar()) {
            $deudaHijos = CuotaAporte::with('estudiante')
                ->whereIn('estudiante_id', $user->estudiantes()->pluck('estudiantes.id'))
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->when(Gestion::actual(), fn ($q) => $q->where('gestion_id', Gestion::actual()->id))
                ->orderBy('anio')->orderBy('mes')
                ->get();
        }

        // El contador de pendientes solo tiene sentido para quien puede
        // validarlos; para los demás enviamos cero.
        return view('aporte.avisos.index', [
            'avisos' => $avisos,
            'deudaHijos' => $deudaHijos,
            'estadoFiltro' => $request->query('estado'),
            'pendientes' => $user->can('aporte.avisos.gestionar')
                ? AvisoPago::where('estado', 'pendiente')->count()
                : 0,
        ]);
    }

    /**
     * Muestra la pantalla donde el responsable familiar informa un pago.
     *
     * Presenta las dos formas de pago: por QR (con el QR y la cuenta del
     * colegio, y el formulario para marcar los meses y subir el comprobante) y
     * en efectivo (instrucciones para pagar en secretaría).
     *
     * @return View Vista `aporte.avisos.create` con las cuotas pendientes, el
     *              total adeudado en centavos, la gestión actual y los datos de pago.
     */
    public function create(Request $request): View
    {
        // Aunque la ruta ya exige el permiso, lo verificamos también aquí como
        // segunda barrera de seguridad.
        abort_unless($request->user()->can('aporte.avisos.informar'), 403);

        $user = $request->user();
        $gestion = Gestion::actual();

        // Cuotas con saldo de sus representados en la gestión actual. Es solo
        // una guía para que la familia sepa cuánto debe; verlas no reduce la
        // deuda. Si no hay gestión activa, devolvemos una colección vacía.
        $cuotasPendientes = $gestion
            ? CuotaAporte::with('estudiante')
                ->whereIn('estudiante_id', $user->estudiantes()->pluck('estudiantes.id'))
                ->where('gestion_id', $gestion->id)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('anio')->orderBy('mes')->get()
            : collect();

        // Sumamos en centavos (enteros) para evitar errores de redondeo propios
        // de los números decimales.
        $totalCentavos = $cuotasPendientes->sum(fn ($c) => $c->saldoCentavos());

        // Meses que ya están en otro aviso pendiente: se muestran deshabilitados
        // para que la familia no los informe dos veces.
        $enAvisoPendiente = AvisoPagoCuota::whereIn('cuota_id', $cuotasPendientes->pluck('id'))
            ->whereHas('aviso', fn ($q) => $q->where('estado', 'pendiente'))
            ->pluck('cuota_id')->all();

        return view('aporte.avisos.create', [
            'cuotasPendientes' => $cuotasPendientes,
            'totalPendiente' => $totalCentavos,
            'gestion' => $gestion,
            'datosPago' => DatosPago::obtener(),
            'enAvisoPendiente' => $enAvisoPendiente,
        ]);
    }

    /**
     * Guarda el aviso de pago por QR informado por el responsable familiar.
     *
     * Valida los meses marcados, la fecha del pago y el comprobante. El tipo
     * del comprobante se comprueba por su contenido real, no solo por la
     * extensión del nombre. Las reglas económicas (meses de sus hijos, montos
     * dentro del saldo, meses no repetidos en otro aviso, comprobante no
     * repetido) las aplica `AporteService::informarAviso()`. La deuda del alumno
     * no se modifica en este paso.
     *
     * @return RedirectResponse Redirige al detalle del aviso recién creado.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.informar'), 403);

        $data = $request->validate([
            'cuotas' => ['required', 'array', 'min:1'],
            'cuotas.*.cuota_id' => ['required', 'integer', 'distinct', 'exists:cuotas_aporte,id'],
            'cuotas.*.monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'fecha_pago' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subYear()->toDateString()],
            'comprobante' => [
                'required', 'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:'.implode(',', AvisoPago::COMPROBANTE_MIMES),
                'max:'.AvisoPago::COMPROBANTE_MAX_KB,
            ],
            'nota' => ['nullable', 'string', 'max:2000'],
        ], [
            'cuotas.required' => 'Marque al menos un mes que esté pagando.',
            'comprobante.mimes' => 'El comprobante debe ser una foto JPG o PNG, o un PDF.',
            'comprobante.mimetypes' => 'El comprobante debe ser una foto JPG o PNG, o un PDF.',
            'fecha_pago.before_or_equal' => 'La fecha del pago no puede ser futura.',
        ], [
            'cuotas' => 'meses a pagar',
            'cuotas.*.monto' => 'monto del mes',
            'fecha_pago' => 'fecha del pago',
            'comprobante' => 'comprobante',
        ]);

        try {
            $aviso = AporteService::informarAviso(
                padre: $request->user(),
                cuotas: array_values($data['cuotas']),
                fechaPago: $data['fecha_pago'],
                comprobante: $request->file('comprobante'),
                nota: $data['nota'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('aporte.avisos.show', $aviso)
            ->with('success', 'Aviso registrado ('.$aviso->referencia.') por '.Dinero::formato($aviso->montoCentavos()).'. La deuda NO cambia hasta que el colegio verifique el pago en su banco y lo valide.');
    }

    /**
     * Entrega el comprobante que subió la familia.
     *
     * Solo lo pueden abrir el responsable que lo subió y el personal autorizado
     * (validación por registro). Se envía con su tipo real y sin permitir que
     * el navegador lo interprete como otro tipo de archivo.
     */
    public function comprobante(Request $request, AvisoPago $aviso): StreamedResponse
    {
        $this->authorizeVer($request->user(), $aviso);
        abort_unless($aviso->tieneComprobante() && Storage::disk('local')->exists($aviso->comprobante_ruta), 404);

        $extension = $aviso->comprobante_mime === 'application/pdf' ? 'pdf' : ($aviso->comprobante_mime === 'image/png' ? 'png' : 'jpg');

        return Storage::disk('local')->response(
            $aviso->comprobante_ruta,
            'comprobante-'.$aviso->referencia.'.'.$extension,
            [
                'Content-Type' => $aviso->comprobante_mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            'inline'
        );
    }

    /**
     * Muestra el detalle de un aviso de pago.
     *
     * La familia solo puede abrir sus propios avisos. Si quien consulta tiene
     * permiso para gestionar avisos y el aviso sigue pendiente, se cargan
     * también las cuotas con saldo de los hijos del responsable, agrupadas por
     * alumno, para que pueda decidir cómo distribuir el pago al validarlo.
     *
     * @param  AvisoPago  $aviso  Aviso que se desea consultar.
     * @return View Vista `aporte.avisos.show` con el aviso y las cuotas por alumno.
     */
    public function show(Request $request, AvisoPago $aviso): View
    {
        $user = $request->user();
        // Validación por registro: comprobamos que este usuario puede ver este
        // aviso en particular, no solo que tiene acceso al módulo.
        $this->authorizeVer($user, $aviso);

        // Cargamos de una vez las relaciones que la vista necesita, incluido el
        // pago generado y sus aplicaciones a cuotas si el aviso ya fue validado.
        $aviso->load(['padre', 'gestion', 'revisor', 'pago.aplicaciones.cuota.estudiante', 'cuotasDeclaradas.cuota.estudiante']);

        // Solo los avisos antiguos, que no declaran meses, necesitan que el
        // operador elija la distribución: para ellos cargamos las cuotas con
        // saldo de los representados de quien avisó, agrupadas por alumno.
        $cuotas = collect();
        if ($user->can('aporte.avisos.gestionar') && $aviso->estaPendiente() && $aviso->cuotasDeclaradas->isEmpty()) {
            // Obtenemos los hijos del responsable que informó el aviso (no los
            // del usuario actual, que aquí es personal institucional).
            $alumnoIds = User::findOrFail($aviso->padre_id)
                ->estudiantes()->pluck('estudiantes.id');

            // Si el aviso tiene gestión asociada, limitamos las cuotas a esa
            // gestión y las ordenamos de la más antigua a la más reciente.
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
     * Valida un aviso de pago por QR, después de verificar el pago en el banco.
     *
     * El operador debe marcar que verificó en la plataforma de su banco que el
     * dinero ingresó y escribir el número de operación bancaria. Si el aviso
     * declara meses, se cancelan esos meses; solo los avisos antiguos traen la
     * distribución desde el formulario. Toda la lógica transaccional (bloqueos,
     * doble procesamiento, número de operación único, suma exacta) vive en
     * `AporteService`, de modo que las pruebas y la interfaz compartan las reglas.
     *
     * @param  AvisoPago  $aviso  Aviso pendiente que se va a validar.
     * @return RedirectResponse Redirige al detalle del pago generado o vuelve
     *                          al formulario con los errores si algo falla.
     */
    public function validar(Request $request, AvisoPago $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);
        $this->authorizeVer($request->user(), $aviso);

        $declaraMeses = $aviso->cuotasDeclaradas()->exists();

        $data = $request->validate([
            'verificado_banco' => ['accepted'],
            'operacion_bancaria' => ['required', 'string', 'max:60'],
            // Solo los avisos sin meses declarados traen la distribución.
            'aplicaciones' => [$declaraMeses ? 'nullable' : 'required', 'array', 'min:1'],
            'aplicaciones.*.cuota_id' => ['required', 'integer', 'exists:cuotas_aporte,id'],
            'aplicaciones.*.monto' => ['required', 'numeric', 'min:0.01'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [
            'verificado_banco.accepted' => 'Antes de validar debe verificar en la plataforma del banco que el dinero ingresó y marcar la casilla.',
        ], [
            'operacion_bancaria' => 'número de operación bancaria',
            'aplicaciones.*.cuota_id' => 'cuota',
            'aplicaciones.*.monto' => 'monto aplicado',
        ]);

        // Delegamos la validación al servicio. Si alguna regla económica no se
        // cumple, el servicio lanza una excepción de validación.
        try {
            $pago = AporteService::validarAviso(
                $aviso,
                $data['aplicaciones'] ?? [],
                $request->user(),
                $data['observacion'] ?? null,
                $data['operacion_bancaria'],
            );
        } catch (ValidationException $e) {
            // Los errores de reglas económicas se muestran al usuario; como todo
            // ocurre dentro de una transacción, no quedan registros a medias.
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('aporte.pagos.show', $pago)
            ->with('success', 'Pago validado y distribuido. Comprobante interno '.$pago->comprobante_numero.' emitido.');
    }

    /**
     * Rechaza un aviso de pago indicando el motivo.
     *
     * El rechazo no toca la deuda del alumno: las cuotas quedan exactamente
     * como estaban. El motivo es obligatorio para que la familia sepa por qué
     * no se aceptó su aviso.
     *
     * @param  AvisoPago  $aviso  Aviso pendiente que se va a rechazar.
     * @return RedirectResponse Vuelve a la página anterior con el resultado.
     */
    public function rechazar(Request $request, AvisoPago $aviso): RedirectResponse
    {
        abort_unless($request->user()->can('aporte.avisos.gestionar'), 403);
        $this->authorizeVer($request->user(), $aviso);

        // El motivo del rechazo es obligatorio y queda guardado en el aviso.
        $data = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ], [], ['motivo_rechazo' => 'motivo del rechazo']);

        // El servicio se encarga de cambiar el estado y registrar la auditoría;
        // si el aviso ya no estaba pendiente, devuelve un error de validación.
        try {
            AporteService::rechazarAviso($aviso, $request->user(), $data['motivo_rechazo']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Aviso rechazado. La deuda del alumno permanece sin cambios.');
    }

    /**
     * Permite que la familia anule su propio aviso mientras siga pendiente.
     *
     * Es útil cuando el responsable se equivocó al informar el monto o la nota.
     * Una vez que el aviso fue validado o rechazado ya no puede anularse desde
     * aquí, porque ya intervino la institución.
     *
     * @param  AvisoPago  $aviso  Aviso que se desea anular.
     * @return RedirectResponse Vuelve a la página anterior con el resultado.
     */
    public function anular(Request $request, AvisoPago $aviso): RedirectResponse
    {
        $user = $request->user();
        // Solo quien informa avisos, y únicamente sobre los avisos que él mismo
        // registró; así nadie puede anular el aviso de otra familia.
        abort_unless($user->can('aporte.avisos.informar'), 403);
        abort_unless($aviso->padre_id === $user->id, 403);

        // Regla de negocio: solo se anula lo que todavía no fue revisado.
        if (! $aviso->estaPendiente()) {
            return back()->with('error', 'Solo puede anular un aviso pendiente.');
        }

        // No borramos el aviso: cambiamos su estado y lo registramos en la
        // auditoría para conservar la trazabilidad.
        $aviso->update(['estado' => 'anulado']);
        AuditoriaService::registrar('aporte.aviso.anular', $aviso, []);

        return back()->with('success', 'Aviso anulado.');
    }

    /**
     * Verifica que el usuario pueda ver un aviso concreto (validación por registro).
     *
     * Las rutas solo comprueban que el usuario tenga acceso al módulo; este
     * método comprueba además el registro puntual. Así, aunque una familia
     * cambie el número del aviso en la URL, no podrá ver avisos de otras
     * familias. Si no tiene acceso, se corta la petición con un error 403.
     *
     * @param  User  $user  Usuario que intenta acceder.
     * @param  AvisoPago  $aviso  Aviso que se quiere consultar o procesar.
     */
    private function authorizeVer(User $user, AvisoPago $aviso): void
    {
        // El responsable familiar solo puede acceder a los avisos que informó.
        if ($user->esResponsableFamiliar()) {
            abort_unless($aviso->padre_id === $user->id, 403);

            return;
        }

        // El personal institucional necesita poder gestionar avisos o, al
        // menos, ver las cuotas del módulo económico.
        abort_unless(
            $user->can('aporte.avisos.gestionar') || $user->can('aporte.cuotas.ver'),
            403
        );
    }
}
