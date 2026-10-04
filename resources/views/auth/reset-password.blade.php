{{--
    Vista: Restablecer contraseña
    Pantalla a la que llega el usuario desde el enlace que recibió por correo
    cuando olvidó su contraseña. Aquí escribe su nueva contraseña dos veces.

    Variables: $request, la petición actual, de donde se toma el token del
    enlace y el correo del usuario.
--}}
<x-guest-layout>
    {{-- Formulario que guarda la nueva contraseña (ruta password.store) --}}
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        {{-- El token oculto demuestra que el usuario llegó desde un enlace válido del correo --}}
        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Nueva contraseña y su confirmación; Laravel valida que ambas coincidan --}}
        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        {{-- Botón para guardar la nueva contraseña --}}
        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
