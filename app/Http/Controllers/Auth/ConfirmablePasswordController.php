<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controlador de confirmación de contraseña.
 *
 * Pertenece al módulo de autenticación. Antes de entrar a ciertas zonas
 * sensibles del sistema se le pide al usuario que vuelva a escribir su
 * contraseña, como una capa extra de seguridad por si dejó la sesión abierta
 * en un equipo compartido. Aplica a cualquier usuario autenticado.
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Muestra la pantalla para confirmar la contraseña.
     *
     * @return View Vista con el formulario de confirmación.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Verifica la contraseña ingresada por el usuario.
     *
     * Se compara la contraseña escrita con la del usuario que tiene la sesión
     * abierta. Si es correcta, se guarda en la sesión la hora de confirmación
     * para no volver a pedirla durante un tiempo.
     *
     * @return RedirectResponse Redirección a la página que se quería visitar.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validamos la contraseña sin iniciar una nueva sesión; solo comprobamos que coincida.
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        // Registramos el momento de la confirmación para que Laravel no la vuelva a pedir enseguida.
        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
