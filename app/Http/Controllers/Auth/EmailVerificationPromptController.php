<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador del aviso de verificación de correo.
 *
 * Forma parte del módulo de autenticación. Es un controlador de una sola
 * acción que decide si se le debe mostrar al usuario la pantalla que le pide
 * verificar su correo electrónico o si ya puede continuar al sistema.
 */
class EmailVerificationPromptController extends Controller
{
    /**
     * Muestra el aviso de verificación o redirige al panel principal.
     *
     * Si el usuario ya verificó su correo, se le envía al dashboard; si no,
     * se le muestra la vista que le explica que debe revisar su bandeja.
     *
     * @return RedirectResponse|View Redirección al dashboard o vista de aviso.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : view('auth.verify-email');
    }
}
