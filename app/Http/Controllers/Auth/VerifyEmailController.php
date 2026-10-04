<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Controlador que confirma la verificación del correo electrónico.
 *
 * Forma parte del módulo de autenticación. Se ejecuta cuando el usuario hace
 * clic en el enlace de verificación que recibió por correo; la clase
 * EmailVerificationRequest ya comprueba que el enlace sea auténtico.
 */
class VerifyEmailController extends Controller
{
    /**
     * Marca el correo del usuario autenticado como verificado.
     *
     * @return RedirectResponse Redirección al dashboard indicando que se verificó.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        // Si ya estaba verificado, no hacemos nada más y lo enviamos al panel principal.
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        // Marcamos el correo como verificado y disparamos el evento correspondiente,
        // por si alguna otra parte del sistema necesita reaccionar a ello.
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
