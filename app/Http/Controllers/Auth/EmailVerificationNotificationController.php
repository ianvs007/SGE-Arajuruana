<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador para reenviar el correo de verificación.
 *
 * Forma parte del módulo de autenticación. Permite que un usuario que
 * todavía no confirmó su dirección de correo solicite que se le envíe
 * nuevamente el enlace de verificación.
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * Envía un nuevo correo de verificación al usuario.
     *
     * Si el correo ya estaba verificado no tiene sentido enviarlo otra vez,
     * así que simplemente se le redirige al panel principal.
     *
     * @return RedirectResponse Regreso a la página anterior con un mensaje de estado.
     */
    public function store(Request $request): RedirectResponse
    {
        // Si ya verificó su correo, lo mandamos directamente al dashboard.
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // En caso contrario, enviamos el enlace de verificación y avisamos que fue enviado.
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
