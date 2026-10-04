{{--
    Vista: Editar usuario.
    Permite modificar los datos de una cuenta, cambiar su rol, activarla o desactivarla y,
    si se desea, asignarle una nueva contraseña.
    Recibe del controlador $user (el usuario a editar) y $roles (roles disponibles).
    La usa el personal con el permiso usuarios.gestionar.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Editar usuario</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Formulario de actualización (PUT); al parcial le pasamos el usuario para rellenar los campos. --}}
                <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    @include('users._form', ['user' => $user])
                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Actualizar</x-primary-button>
                        <a href="{{ route('users.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
