<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Controlador del perfil del usuario.
 *
 * Atiende la sección "Mi perfil", disponible para cualquier usuario que haya
 * iniciado sesión, sin importar su rol. Desde aquí cada persona puede ver y
 * actualizar sus propios datos (nombre y correo) o eliminar su cuenta.
 */
class ProfileController extends Controller
{
    /**
     * Muestra el formulario con los datos del perfil del usuario.
     *
     * @return View Vista de edición del perfil con el usuario autenticado.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Actualiza el nombre y el correo del usuario.
     *
     * Los datos llegan ya validados por ProfileUpdateRequest. Si el usuario
     * cambió su correo, se marca como no verificado, porque la nueva dirección
     * todavía no fue comprobada.
     *
     * @return RedirectResponse Regreso al perfil con mensaje de éxito.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Cargamos en el modelo los datos validados, pero todavía sin guardarlos.
        $request->user()->fill($request->validated());

        // Si el correo cambió, quitamos la marca de verificado para que se vuelva a confirmar.
        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Elimina la cuenta del usuario autenticado.
     *
     * Como es una acción irreversible, se pide la contraseña actual para
     * confirmar que realmente es el dueño de la cuenta quien lo solicita.
     * Después se cierra la sesión y se borra el usuario.
     *
     * @return RedirectResponse Redirección a la página de inicio.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Exigimos la contraseña actual; los errores van a su propio grupo para el modal de eliminación.
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        // Guardamos la referencia al usuario antes de cerrar la sesión, porque luego ya no estará disponible.
        $user = $request->user();

        Auth::logout();

        $user->delete();

        // Invalidamos la sesión y renovamos el token para no dejar rastros de la cuenta eliminada.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
