{{--
    Vista: Nueva gestión académica.
    Pantalla donde el administrador registra una nueva gestión (año escolar), por ejemplo
    "Gestión 2026". No recibe variables especiales del controlador: el formulario se
    comparte con la vista de edición a través del parcial gestiones._form.
    La usa el personal con permiso para administrar gestiones (normalmente el administrador).
--}}
<x-app-layout>
    {{-- Encabezado de la página que se muestra en la barra superior del layout principal. --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Nueva gestión</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes de éxito o error que deja el controlador en la sesión. --}}
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{--
                    Formulario de registro. Se envía por POST a gestiones.store; @csrf agrega el
                    token de seguridad que Laravel exige en todos los formularios.
                --}}
                <form method="POST" action="{{ route('gestiones.store') }}" class="space-y-4">
                    @csrf
                    @include('gestiones._form')
                    {{-- Botones para guardar o volver al listado sin registrar nada. --}}
                    <div class="flex gap-3">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('gestiones.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
