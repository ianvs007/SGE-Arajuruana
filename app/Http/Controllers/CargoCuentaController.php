<?php

namespace App\Http\Controllers;

use App\Models\CargoCuenta;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CargoCuentaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = CargoCuenta::with(['padre', 'estudiante']);

        if ($user->esResponsableFamiliar()) {
            $query->where('padre_id', $user->id);
        }

        $cargos = $query->latest('fecha_emision')->paginate(15);

        return view('cuentas.index', compact('cargos'));
    }

    public function create(): View
    {
        return view('cuentas.create', [
            'padres' => User::role(User::ROL_RESPONSABLE)->orderBy('name')->get(),
            'estudiantes' => Estudiante::orderBy('apellidos')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'padre_id' => ['required', 'exists:users,id'],
            'estudiante_id' => ['nullable', 'exists:estudiantes,id'],
            'concepto' => ['required', 'string', 'max:180'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string'],
        ]);

        CargoCuenta::create([
            ...$data,
            'estado' => 'pendiente',
            'creado_por' => $request->user()->id,
        ]);

        return redirect()->route('cuentas.index')->with('success', 'Cargo registrado.');
    }

    public function show(Request $request, CargoCuenta $cuenta): View
    {
        if ($request->user()->esResponsableFamiliar() && $cuenta->padre_id !== $request->user()->id) {
            abort(403);
        }

        $cuenta->load(['padre', 'estudiante', 'pagos.confirmador']);

        return view('cuentas.show', ['cargo' => $cuenta]);
    }
}
