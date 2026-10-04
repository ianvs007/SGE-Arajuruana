{{--
    Vista: Editar citación
    Permite modificar una citación existente reutilizando el formulario parcial
    citaciones/_form con los datos actuales.

    Variables que recibe del controlador: $citacion (la citación a editar),
    además de $estudiantes, $incidencias, $usuarios y $estados para el formulario.
--}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar citación</h2></x-slot>
    <div class="py-8"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8"><div class="bg-white shadow-sm rounded-lg p-6">
        {{-- Formulario de actualización; se envía con PUT porque modifica un registro existente --}}
        <form method="POST" action="{{ route('citaciones.update', $citacion) }}" class="space-y-4">@csrf @method('PUT') @include('citaciones._form', ['citacion'=>$citacion]) <x-primary-button>Actualizar</x-primary-button></form>
    </div></div></div>
</x-app-layout>
