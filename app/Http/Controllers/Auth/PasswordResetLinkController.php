<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controlador para solicitar el enlace de recuperación de contraseña.
 *
 * Pertenece al módulo de autenticación y es la primera parte del proceso de
 * "Olvidé mi contraseña": el usuario escribe su correo y el sistema le envía
 * un enlace para crear una contraseña nueva.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Muestra el formulario donde se ingresa el correo para recuperar la contraseña.
     *
     * @return View Vista de "Olvidé mi contraseña".
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Envía el enlace de recuperación al correo indicado.
     *
     * Si el servidor de correo falla (por ejemplo, porque no hay internet o la
     * configuración SMTP está mal), no queremos que el usuario vea un error
     * técnico del sistema. En ese caso se le muestra un mensaje claro y se
     * conserva el correo que escribió para que pueda intentarlo de nuevo.
     *
     * @return RedirectResponse Regreso al formulario con mensaje de éxito o de error.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Comprobamos que el correo venga y tenga un formato válido.
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Intentamos enviar el enlace de recuperación. Laravel nos devuelve un estado
        // que luego revisamos para saber qué mensaje mostrarle al usuario.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Throwable $e) {
            // Si el envío de correo falla (sin internet o SMTP mal configurado), mostramos
            // un mensaje entendible sin revelar detalles internos y sin fingir que el
            // correo se envió correctamente.
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'No se pudo enviar el correo de recuperación. '
                    .'Verifique su conexión o la configuración MAIL_* en .env e intente de nuevo.']);
        }

        // Según el estado devuelto, informamos que el enlace fue enviado o mostramos el error.
        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
