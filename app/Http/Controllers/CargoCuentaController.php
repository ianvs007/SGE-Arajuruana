<?php

namespace App\Http\Controllers;

use App\Models\CargoCuenta;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador de los cargos de cuenta del módulo económico anterior.
 *
 * Un cargo de cuenta es un monto que el colegio le asigna a un responsable
 * familiar (por ejemplo, una mensualidad o un cobro extraordinario), opcionalmente
 * vinculado a un alumno. Este controlador permite listar, crear y ver el
 * detalle de esos cargos junto con los pagos que se registraron sobre ellos.
 *
 * Este flujo es el de la Etapa 2 y lo conservamos para no perder los datos
 * históricos y para los cargos extraordinarios que no forman parte del aporte
 * mensual, que ahora se maneja con `AportePagoController` y las cuotas.
 *
 * Roles: Administración, Director y Coordinadora crean cargos (permiso
 * `cuentas.gestionar`); el responsable familiar, con `cuentas.ver`, solo
 * consulta los cargos que están a su nombre.
 */
class CargoCuentaController extends Controller
{
    /**
     * Muestra el listado paginado de cargos de cuenta.
     *
     * El responsable familiar solo ve sus propios cargos; el personal
     * institucional ve todos.
     *
     * @return View Vista `cuentas.index` con los cargos.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = CargoCuenta::with(['padre', 'estudiante']);

        // Validación por registro: el responsable familiar solo ve los cargos a su nombre.
        if ($user->esResponsableFamiliar()) {
            $query->where('padre_id', $user->id);
        }

        // Mostramos primero los cargos emitidos más recientemente.
        $cargos = $query->latest('fecha_emision')->paginate(15);

        return view('cuentas.index', compact('cargos'));
    }

    /**
     * Muestra el formulario para registrar un nuevo cargo.
     *
     * Se cargan los usuarios con rol de responsable familiar (a quienes se
     * asigna el cargo) y la lista de estudiantes por si el cargo corresponde
     * a un alumno en particular.
     *
     * @return View Vista `cuentas.create`.
     */
    public function create(): View
    {
        return view('cuentas.create', [
            'padres' => User::role(User::ROL_RESPONSABLE)->orderBy('name')->get(),
            'estudiantes' => Estudiante::orderBy('apellidos')->get(),
        ]);
    }

    /**
     * Guarda un nuevo cargo de cuenta.
     *
     * El cargo siempre nace en estado "pendiente" y se registra quién lo creó,
     * para saber más adelante de dónde salió cada cobro.
     *
     * @return RedirectResponse Redirige al listado de cargos.
     */
    public function store(Request $request): RedirectResponse
    {
        // El alumno es opcional porque hay cargos que no dependen de un
        // estudiante concreto; el monto debe ser mayor a cero.
        $data = $request->validate([
            'padre_id' => ['required', 'exists:users,id'],
            'estudiante_id' => ['nullable', 'exists:estudiantes,id'],
            'concepto' => ['required', 'string', 'max:180'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string'],
        ]);

        // El estado y el creador los fija el sistema, no el formulario.
        CargoCuenta::create([
            ...$data,
            'estado' => 'pendiente',
            'creado_por' => $request->user()->id,
        ]);

        return redirect()->route('cuentas.index')->with('success', 'Cargo registrado.');
    }

    /**
     * Muestra el detalle de un cargo con los pagos registrados sobre él.
     *
     * @param  CargoCuenta  $cuenta  Cargo obtenido por la ruta `cuentas/{cuenta}`.
     * @return View Vista `cuentas.show`.
     */
    public function show(Request $request, CargoCuenta $cuenta): View
    {
        // Un responsable familiar no puede ver cargos que no están a su nombre,
        // aunque intente abrirlos cambiando el id en la URL.
        if ($request->user()->esResponsableFamiliar() && $cuenta->padre_id !== $request->user()->id) {
            abort(403);
        }

        // Cargamos el responsable, el alumno y los pagos con quien los confirmó.
        $cuenta->load(['padre', 'estudiante', 'pagos.confirmador']);

        return view('cuentas.show', ['cargo' => $cuenta]);
    }
}
