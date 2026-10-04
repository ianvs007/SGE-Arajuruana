<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Solicitud de inicio de sesión del sistema.
 *
 * Esta clase concentra todo lo relacionado con el login: valida los datos
 * del formulario, intenta autenticar al usuario con su correo y contraseña,
 * impide el ingreso de cuentas desactivadas y limita la cantidad de intentos
 * fallidos para protegernos de ataques de fuerza bruta. La usan todos los
 * roles del sistema, ya que es la única puerta de entrada.
 */
class LoginRequest extends FormRequest
{
    /**
     * Indica si el usuario puede hacer esta solicitud.
     *
     * Devolvemos siempre verdadero porque el login, por naturaleza, debe estar
     * disponible para cualquier visitante que todavía no inició sesión.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación del formulario de inicio de sesión.
     *
     * Solo exigimos que el correo tenga un formato válido y que la contraseña
     * no venga vacía; la comprobación real de las credenciales se hace después
     * en el método authenticate().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Intenta autenticar al usuario con las credenciales enviadas.
     *
     * Primero se revisa que no haya superado el límite de intentos. Luego se
     * comparan el correo y la contraseña con la base de datos; si no coinciden
     * se suma un intento fallido. Si coinciden, todavía verificamos que la
     * cuenta esté activa antes de dejarlo pasar.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        // Antes de nada comprobamos que el usuario no esté bloqueado temporalmente
        // por haber fallado demasiadas veces.
        $this->ensureIsNotRateLimited();

        // Intentamos iniciar sesión con correo y contraseña (y la opción "recordarme").
        // Si falla, registramos el intento en el limitador y mostramos el error genérico.
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Una cuenta que fue inactivada por Administración no puede ingresar al sistema,
        // aunque sus credenciales sean correctas. Por eso cerramos de inmediato la sesión
        // que se acaba de abrir y le mostramos un mensaje explicando el motivo.
        if (! Auth::user()->activo) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta está inactiva. Contacte a Administración.',
            ]);
        }

        // Si el ingreso fue exitoso, limpiamos el contador de intentos fallidos.
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Verifica que el usuario no haya superado el límite de intentos.
     *
     * Permitimos hasta 5 intentos fallidos; a partir de ahí se bloquea el
     * acceso durante un tiempo y se le informa cuántos segundos o minutos
     * debe esperar antes de volver a intentarlo.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // Si todavía no llegó a los 5 intentos, no hacemos nada y dejamos continuar.
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        // Disparamos el evento de bloqueo por si se quiere registrar o notificar.
        event(new Lockout($this));

        // Calculamos cuánto tiempo falta para que se libere el bloqueo.
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Genera la clave con la que se cuentan los intentos de inicio de sesión.
     *
     * La clave combina el correo (en minúsculas y sin caracteres especiales)
     * con la dirección IP, de modo que el bloqueo afecte solo a esa cuenta
     * desde ese equipo y no a todos los usuarios.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
