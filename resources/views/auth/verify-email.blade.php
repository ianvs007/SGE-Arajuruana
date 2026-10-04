{{--
    Vista: Verificación de correo electrónico
    Se muestra a los usuarios que todavía no confirmaron su correo. Les pide
    hacer clic en el enlace que se les envió y les permite solicitar uno nuevo
    o cerrar sesión. No recibe variables propias del controlador.
--}}
<x-guest-layout>
    {{-- Instrucciones para el usuario --}}
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    {{-- Confirmación que aparece solo después de reenviar el enlace de verificación --}}
    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        {{-- Formulario para reenviar el correo de verificación --}}
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        {{-- Formulario para cerrar sesión; se usa POST para que no se pueda cerrar sesión con un simple enlace --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
