<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Controlador para cambiar la contraseña desde el perfil.
 *
 * Forma parte del módulo de autenticación. Lo usa cualquier usuario que ya
 * inició sesión y desea cambiar su contraseña desde la pantalla "Mi perfil".
 */
class PasswordController extends Controller
{
    /**
     * Actualiza la contraseña del usuario autenticado.
     *
     * Para cambiarla se exige la contraseña actual (así evitamos que otra
     * persona la cambie si encuentra la sesión abierta) y la nueva contraseña
     * confirmada, cumpliendo las reglas mínimas de seguridad.
     *
     * @return RedirectResponse Regreso al perfil con un mensaje de confirmación.
     */
    public function update(Request $request): RedirectResponse
    {
        // Validamos en un grupo de errores propio ("updatePassword") para que los mensajes
        // aparezcan solo en el formulario de contraseña y no en el de datos del perfil.
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Guardamos la nueva contraseña siempre cifrada, nunca en texto plano.
        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
