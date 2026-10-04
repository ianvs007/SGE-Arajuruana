{{--
    Vista: Perfil del usuario.
    Pantalla donde cualquier usuario que inició sesión puede actualizar sus datos personales,
    cambiar su contraseña o eliminar su cuenta. Se basa en la estructura que trae Laravel Breeze
    y está dividida en tres tarjetas, cada una con su propio parcial y su propio formulario.
    Recibe del controlador $user, el usuario autenticado, que usan los parciales.
    Los textos se muestran con __() para que Laravel los traduzca al español.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Tarjeta 1: nombre y correo electrónico. --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- Tarjeta 2: cambio de contraseña. --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Tarjeta 3: eliminación de la cuenta. --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
