<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Support\Permisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Administración de cuentas de usuario (§5, §6).
 *
 * Salvaguarda anti-escalada: nadie puede asignar un rol de rango mayor o igual al
 * propio (salvo Administración, que es el rango máximo y sí puede crearse pares),
 * ni modificar/inactivar cuentas de rango mayor o igual.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $users = User::with('roles')
            ->when($q, fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('documento', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('users.index', compact('users', 'q'));
    }

    public function create(Request $request): View
    {
        return view('users.create', [
            'roles' => $this->rolesAsignables($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $asignables = $this->rolesAsignables($request->user())->pluck('name');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'documento' => ['nullable', 'string', 'max:30', 'unique:users,documento'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($asignables)],
            'activo' => ['nullable', 'boolean'],
        ], [
            'role.in' => 'No tiene autorización para asignar ese rol.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'documento' => $data['documento'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'password' => Hash::make($data['password']),
            'activo' => $request->boolean('activo', true),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$data['role']]);
        AuditoriaService::registrar('usuarios.crear', $user, ['email' => $user->email, 'rol' => $data['role']]);

        return redirect()->route('users.index')->with('success', 'Usuario registrado correctamente.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->autorizarGestion($request->user(), $user);

        return view('users.edit', [
            'user' => $user->load('roles'),
            'roles' => $this->rolesAsignables($request->user()),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->autorizarGestion($request->user(), $user);

        $asignables = $this->rolesAsignables($request->user())->pluck('name');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'documento' => ['nullable', 'string', 'max:30', Rule::unique('users', 'documento')->ignore($user->id)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($asignables)],
            'activo' => ['nullable', 'boolean'],
        ], [
            'role.in' => 'No tiene autorización para asignar ese rol.',
        ]);

        $rolAnterior = $user->roles->first()?->name;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'documento' => $data['documento'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'activo' => $request->boolean('activo', true),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        if ($rolAnterior !== $data['role']) {
            AuditoriaService::registrar('usuarios.asignar_rol', $user, [
                'email' => $user->email,
                'rol_anterior' => $rolAnterior,
                'rol_nuevo' => $data['role'],
            ]);
        } else {
            AuditoriaService::registrar('usuarios.actualizar', $user, ['email' => $user->email]);
        }

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * §6/§7: las cuentas se inactivan (trazable), no se eliminan físicamente
     * cuando tienen movimientos asociados.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puede eliminar su propio usuario.');
        }

        $this->autorizarGestion($request->user(), $user);

        $tieneMovimientos = $user->estudiantes()->exists()
            || $user->cargos()->exists()
            || $user->pagos()->exists();

        if ($tieneMovimientos) {
            $user->update(['activo' => false]);
            AuditoriaService::registrar('usuarios.inactivar', $user, ['email' => $user->email]);

            return redirect()->route('users.index')->with('success', 'Usuario inactivado (conserva su historial).');
        }

        $email = $user->email;
        $user->delete();
        AuditoriaService::registrar('usuarios.eliminar_sin_movimientos', null, ['email' => $email]);

        return redirect()->route('users.index')->with('success', 'Usuario eliminado.');
    }

    /**
     * Roles que el actor puede asignar: estrictamente de menor rango que el suyo,
     * salvo Administración que puede asignar cualquier rol (es el rango máximo).
     */
    private function rolesAsignables(User $actor): \Illuminate\Support\Collection
    {
        $rangoActor = $actor->rangoMaximo();
        $esAdmin = $actor->esAdministracion();

        return Role::orderBy('name')->get()->filter(function (Role $rol) use ($esAdmin, $rangoActor) {
            return $esAdmin || Permisos::rango($rol->name) < $rangoActor;
        })->values();
    }

    /**
     * Nadie gestiona cuentas de rango mayor o igual al propio (salvo Administración
     * sobre cuentas de rango menor o pares). No se puede gestionar la propia cuenta.
     * Regla centralizada en User::puedeGestionar() (§5, §18).
     */
    private function autorizarGestion(User $actor, User $objetivo): void
    {
        abort_unless($actor->puedeGestionar($objetivo), 403, 'No tiene autorización para gestionar esta cuenta.');
    }
}
