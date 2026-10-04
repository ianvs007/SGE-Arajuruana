<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controlador de sesiones de usuario (inicio y cierre de sesión).
 *
 * Forma parte del módulo de autenticación. Se encarga de mostrar el
 * formulario de login, de abrir la sesión cuando las credenciales son
 * correctas y de cerrarla de forma segura. Lo usan todos los roles del
 * sistema, ya que es el punto de entrada y salida de la aplicación.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Muestra la pantalla de inicio de sesión.
     *
     * @return View Vista con el formulario de login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Procesa el formulario de inicio de sesión.
     *
     * La validación y la comprobación de credenciales (incluido el control de
     * cuentas inactivas y de intentos fallidos) se delegan al LoginRequest.
     * Si todo es correcto, se regenera la sesión y se envía al usuario al
     * panel principal o a la página que intentaba visitar.
     *
     * @return RedirectResponse Redirección al dashboard o a la ruta solicitada.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Validamos las credenciales; si algo falla, el propio request lanza el error.
        $request->authenticate();

        // Regeneramos el identificador de sesión para evitar ataques de fijación de sesión.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Cierra la sesión del usuario autenticado.
     *
     * Además de cerrar la sesión, se invalida por completo y se genera un
     * nuevo token CSRF, para que nadie pueda reutilizar los datos de la
     * sesión anterior en ese navegador.
     *
     * @return RedirectResponse Redirección a la página de inicio.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Borramos los datos de la sesión y renovamos el token de seguridad de los formularios.
        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
