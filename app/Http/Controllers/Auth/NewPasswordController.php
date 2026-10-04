<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controlador para establecer una nueva contraseña.
 *
 * Pertenece al módulo de autenticación y es la segunda parte del proceso de
 * "Olvidé mi contraseña": cuando el usuario abre el enlace que recibió por
 * correo, este controlador le muestra el formulario y guarda la nueva
 * contraseña si el enlace (token) es válido.
 */
class NewPasswordController extends Controller
{
    /**
     * Muestra el formulario para escribir la nueva contraseña.
     *
     * Se le pasa a la vista la petición completa porque en ella viene el token
     * y el correo que llegaron en el enlace de recuperación.
     *
     * @return View Vista con el formulario de restablecimiento.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Guarda la nueva contraseña del usuario.
     *
     * Se valida el token, el correo y la nueva contraseña (que debe venir
     * confirmada y cumplir las reglas mínimas de seguridad). Si el token es
     * válido, se actualiza la contraseña y se le envía al login.
     *
     * @return RedirectResponse Redirección al login o de regreso con el error.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Validamos que vengan todos los datos y que la contraseña sea segura y esté confirmada.
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Intentamos restablecer la contraseña. Si el token es válido, Laravel ejecuta
        // la función que le pasamos: guardamos la contraseña cifrada y generamos un nuevo
        // "remember_token" para invalidar las sesiones recordadas en otros equipos.
        // Si algo falla, recibimos un estado de error que mostraremos al usuario.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // Si la contraseña se cambió correctamente, lo mandamos al login con un mensaje
        // de éxito; si hubo un error (token vencido, correo incorrecto, etc.), regresamos
        // al formulario mostrando el motivo.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
