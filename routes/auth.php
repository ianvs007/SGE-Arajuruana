<?php

/*
|--------------------------------------------------------------------------
| Rutas de autenticación
|--------------------------------------------------------------------------
|
| Aquí están las rutas relacionadas con el acceso al sistema: inicio y cierre
| de sesión, recuperación de contraseña, verificación del correo electrónico y
| confirmación de contraseña. Partimos de la estructura que trae Laravel
| Breeze, pero quitamos el registro público porque en el colegio las cuentas
| las crea el personal autorizado desde el panel de usuarios.
|
*/

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// Rutas para visitantes (middleware "guest"): solo se pueden usar si todavía no
// se ha iniciado sesión. Si un usuario ya autenticado intenta entrar aquí, se
// le redirige a su panel.
Route::middleware('guest')->group(function () {
    // Registro público deshabilitado: los usuarios se crean desde el panel institucional.

    // Formulario de inicio de sesión y envío de las credenciales.
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // "Olvidé mi contraseña": muestra el formulario y envía al correo del
    // usuario un enlace para restablecerla.
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    // Formulario para escribir la nueva contraseña. El {token} viene en el
    // enlace del correo y sirve para comprobar que la solicitud es legítima.
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

// Rutas para usuarios que ya iniciaron sesión (middleware "auth").
Route::middleware('auth')->group(function () {
    // Aviso que pide al usuario verificar su correo electrónico.
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    // Enlace de verificación que llega por correo. "signed" comprueba que la
    // URL no haya sido alterada y "throttle:6,1" limita a 6 intentos por
    // minuto para evitar abusos.
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Reenvío del correo de verificación, también con límite de intentos.
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Confirmación de contraseña: se pide antes de acciones delicadas, como
    // medida extra de seguridad aunque la sesión ya esté abierta.
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Cambio de contraseña desde el perfil del propio usuario.
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // Cierre de sesión. Usamos POST (y no GET) para que esté protegido con el
    // token CSRF y no se pueda cerrar la sesión de alguien con un simple enlace.
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
