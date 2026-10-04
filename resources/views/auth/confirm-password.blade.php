{{--
    Vista: Confirmar contraseña
    Se muestra cuando el usuario intenta entrar a una zona protegida que pide
    volver a escribir la contraseña como medida de seguridad adicional.
    No recibe variables propias del controlador.
--}}
<x-guest-layout>
    {{-- Mensaje que explica por qué se pide de nuevo la contraseña --}}
    <div class="mb-4 text-sm text-gray-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    {{-- Formulario que verifica la contraseña actual antes de continuar --}}
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
