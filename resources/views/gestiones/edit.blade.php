{{--
    Vista: Editar gestión académica.
    Permite modificar los datos de una gestión ya registrada (nombre, año, fechas, estado).
    Recibe del controlador la variable $gestion con el registro a editar, que el parcial
    gestiones._form usa para rellenar los campos.
    La usa el personal con permiso para administrar gestiones.
--}}
<x-app-layout>
    {{-- Título de la página en el encabezado del layout. --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Editar gestión</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes flash (éxito o error) del último proceso. --}}
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{--
                    Como los formularios HTML solo admiten GET y POST, usamos @method('PUT') para
                    que Laravel lo trate como una actualización y lo dirija a gestiones.update.
                --}}
                <form method="POST" action="{{ route('gestiones.update', $gestion) }}" class="space-y-4">
                    @csrf @method('PUT')
                    @include('gestiones._form')
                    {{-- Acciones: guardar los cambios o cancelar y regresar al listado. --}}
                    <div class="flex gap-3">
                        <x-primary-button>Guardar cambios</x-primary-button>
                        <a href="{{ route('gestiones.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
