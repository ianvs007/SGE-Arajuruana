{{--
    Vista: Inicio de sesión
    Es la pantalla de entrada al sistema. Aquí todos los usuarios (dirección,
    secretaría, docentes y responsables familiares) escriben su correo y su
    contraseña para ingresar. Usa el layout de invitado (guest-layout) porque
    el usuario todavía no está autenticado.

    No recibe variables propias del controlador; solo usa session('status') para
    mensajes y $errors para los errores de validación que devuelve Laravel.
--}}
<x-guest-layout>
    {{-- Encabezado con el nombre del sistema y de la unidad educativa --}}
    <div class="mb-4 text-center">
        <h1 class="text-lg font-semibold text-slate-800">Sistema de Gestión Educativa</h1>
        <p class="text-sm text-slate-500">Unidad Educativa Arajuruana</p>
    </div>

    {{-- Mensaje de estado, por ejemplo después de restablecer la contraseña --}}
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{--
        Formulario de acceso. Se envía por POST a la ruta "login"; el token @csrf
        protege el formulario contra peticiones falsificadas desde otros sitios.
    --}}
    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Correo electrónico; old('email') conserva lo escrito si la validación falla --}}
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Contraseña del usuario --}}
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Casilla "Recordarme" para mantener la sesión abierta en este equipo --}}
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-sky-700 shadow-sm focus:ring-sky-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Recordarme</span>
            </label>
        </div>

        {{-- Botón para enviar las credenciales --}}
        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Iniciar sesión
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
