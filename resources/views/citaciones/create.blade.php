{{--
    Vista: Nueva citación
    Pantalla para registrar una citación a un responsable familiar. Los campos
    están en la vista parcial citaciones/_form, que también usa la edición.

    Variables que recibe del controlador (se pasan al formulario parcial):
    $estudiantes, $incidencias, $usuarios y $estados.
--}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva citación</h2></x-slot>
    <div class="py-8"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8"><div class="bg-white shadow-sm rounded-lg p-6">
        {{-- Formulario que guarda la citación con los campos del formulario parcial --}}
        <form method="POST" action="{{ route('citaciones.store') }}" class="space-y-4">@csrf @include('citaciones._form') <x-primary-button>Guardar</x-primary-button></form>
    </div></div></div>
</x-app-layout>
