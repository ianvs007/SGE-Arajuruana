{{--
    Vista: Nuevo usuario.
    Pantalla donde el administrador crea una cuenta de acceso al sistema (para un docente, un
    padre de familia, personal administrativo, etc.) y le asigna un rol. El rol define qué
    módulos podrá ver y usar.
    Los campos están en el parcial users._form, que necesita la variable $roles del controlador.
    La usa el personal con el permiso usuarios.gestionar.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Nuevo usuario</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Formulario de registro con los campos del parcial compartido y los botones de guardar o cancelar. --}}
                <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                    @csrf
                    @include('users._form')
                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('users.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
