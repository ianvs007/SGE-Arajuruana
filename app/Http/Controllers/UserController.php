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
 * Controlador de administración de cuentas de usuario.
 *
 * Atiende el módulo "Usuarios", al que solo acceden quienes tienen el permiso
 * "usuarios.gestionar" (principalmente Administración). Desde aquí se crean,
 * editan, inactivan o eliminan las cuentas y se les asigna un rol.
 *
 * Incluimos una protección contra la escalada de privilegios: nadie puede
 * asignar un rol de rango mayor o igual al suyo, ni modificar o inactivar
 * cuentas de rango mayor o igual. La única excepción es Administración, que
 * tiene el rango máximo y por eso sí puede crear y gestionar a otros
 * administradores.
 */
class UserController extends Controller
{
    /**
     * Muestra el listado de usuarios con un buscador.
     *
     * La búsqueda es opcional y se aplica sobre el nombre, el correo o el
     * número de documento. El resultado se ordena alfabéticamente y se pagina.
     *
     * @return View Vista con el listado de usuarios y el texto buscado.
     */
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();

        // Si se escribió algo en el buscador, filtramos por nombre, correo o documento.
        // Agrupamos las condiciones para que los "o" no se mezclen con otros filtros.
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

    /**
     * Muestra el formulario para registrar un nuevo usuario.
     *
     * En la lista de roles solo aparecen los que el usuario actual tiene
     * autorizado asignar, según su propio rango.
     *
     * @return View Vista del formulario de creación.
     */
    public function create(Request $request): View
    {
        return view('users.create', [
            'roles' => $this->rolesAsignables($request->user()),
        ]);
    }

    /**
     * Registra un nuevo usuario en el sistema.
     *
     * Se validan los datos y, sobre todo, que el rol elegido esté entre los
     * que el usuario actual puede asignar; así, aunque alguien manipule el
     * formulario, el servidor rechaza un rol no permitido. La cuenta se crea
     * con el correo ya verificado porque la da de alta Administración.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function store(Request $request): RedirectResponse
    {
        // Obtenemos solo los nombres de los roles permitidos para usarlos en la validación.
        $asignables = $this->rolesAsignables($request->user())->pluck('name');

        // Validamos los datos del formulario; el correo y el documento no pueden repetirse.
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

        // Creamos la cuenta con la contraseña cifrada. Si no se marca lo contrario,
        // el usuario queda activo desde el principio.
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

        // Asignamos el rol (cada usuario tiene uno solo) y lo registramos en la auditoría.
        $user->syncRoles([$data['role']]);
        AuditoriaService::registrar('usuarios.crear', $user, ['email' => $user->email, 'rol' => $data['role']]);

        return redirect()->route('users.index')->with('success', 'Usuario registrado correctamente.');
    }

    /**
     * Muestra el formulario para editar un usuario.
     *
     * Antes de mostrarlo se comprueba que el usuario actual tenga autoridad
     * sobre la cuenta que quiere editar.
     *
     * @return View Vista del formulario de edición.
     */
    public function edit(Request $request, User $user): View
    {
        $this->autorizarGestion($request->user(), $user);

        return view('users.edit', [
            'user' => $user->load('roles'),
            'roles' => $this->rolesAsignables($request->user()),
        ]);
    }

    /**
     * Actualiza los datos y el rol de un usuario.
     *
     * La contraseña es opcional al editar: si se deja vacía se conserva la
     * actual. Si el rol cambió, lo registramos en la auditoría de forma
     * especial, ya que un cambio de rol modifica lo que esa persona puede
     * hacer en el sistema.
     *
     * @return RedirectResponse Redirección al listado con mensaje de éxito.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        // Volvemos a verificar la autoridad en el servidor, no solo al mostrar el formulario.
        $this->autorizarGestion($request->user(), $user);

        $asignables = $this->rolesAsignables($request->user())->pluck('name');

        // Validamos ignorando al propio usuario en las reglas de correo y documento únicos.
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

        // Guardamos el rol que tenía antes para saber después si hubo un cambio de rol.
        $rolAnterior = $user->roles->first()?->name;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'documento' => $data['documento'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'activo' => $request->boolean('activo', true),
        ]);

        // Solo cambiamos la contraseña si se escribió una nueva.
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        // En la auditoría distinguimos un cambio de rol (guardando el anterior y el nuevo)
        // de una simple actualización de datos.
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
     * Elimina o inactiva una cuenta de usuario.
     *
     * Si la cuenta tiene movimientos asociados (estudiantes vinculados, cargos
     * o pagos), no se borra físicamente: se inactiva, para no perder el
     * historial ni romper la trazabilidad. Solo se elimina de verdad cuando
     * no tiene ningún registro relacionado.
     *
     * @return RedirectResponse Redirección al listado o regreso con un error.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Evitamos que alguien se elimine a sí mismo y se quede sin acceso al sistema.
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puede eliminar su propio usuario.');
        }

        $this->autorizarGestion($request->user(), $user);

        // Revisamos si el usuario tiene información relacionada en el sistema.
        $tieneMovimientos = $user->estudiantes()->exists()
            || $user->cargos()->exists()
            || $user->pagos()->exists();

        // Con movimientos: solo lo inactivamos, así no podrá ingresar pero su historial se conserva.
        if ($tieneMovimientos) {
            $user->update(['activo' => false]);
            AuditoriaService::registrar('usuarios.inactivar', $user, ['email' => $user->email]);

            return redirect()->route('users.index')->with('success', 'Usuario inactivado (conserva su historial).');
        }

        // Sin movimientos: lo eliminamos. Guardamos antes el correo para dejarlo en la auditoría,
        // porque después de borrar el registro ya no lo tendremos.
        $email = $user->email;
        $user->delete();
        AuditoriaService::registrar('usuarios.eliminar_sin_movimientos', null, ['email' => $email]);

        return redirect()->route('users.index')->with('success', 'Usuario eliminado.');
    }

    /**
     * Obtiene los roles que el usuario actual puede asignar.
     *
     * Un usuario solo puede asignar roles de rango estrictamente menor que
     * el suyo. Administración es la excepción: al tener el rango máximo puede
     * asignar cualquier rol. Los rangos de cada rol están definidos en la
     * clase Permisos.
     *
     * @param  User  $actor  Usuario que está realizando la operación.
     * @return \Illuminate\Support\Collection Roles permitidos, ordenados por nombre.
     */
    private function rolesAsignables(User $actor): \Illuminate\Support\Collection
    {
        $rangoActor = $actor->rangoMaximo();
        $esAdmin = $actor->esAdministracion();

        // Recorremos todos los roles y nos quedamos solo con los que el actor puede otorgar.
        return Role::orderBy('name')->get()->filter(function (Role $rol) use ($esAdmin, $rangoActor) {
            return $esAdmin || Permisos::rango($rol->name) < $rangoActor;
        })->values();
    }

    /**
     * Verifica que el usuario actual pueda gestionar la cuenta indicada.
     *
     * Nadie puede gestionar cuentas de rango mayor o igual al suyo, salvo
     * Administración, que también puede gestionar a sus pares. Tampoco se
     * puede gestionar la propia cuenta desde este módulo. La regla está
     * centralizada en User::puedeGestionar() para aplicarla igual en todo el
     * sistema; si no se cumple, se responde con un error 403.
     *
     * @param  User  $actor  Usuario que realiza la operación.
     * @param  User  $objetivo  Cuenta que se quiere modificar.
     */
    private function autorizarGestion(User $actor, User $objetivo): void
    {
        abort_unless($actor->puedeGestionar($objetivo), 403, 'No tiene autorización para gestionar esta cuenta.');
    }
}
