{{--
    Vista: Nueva incidencia.
    Pantalla para registrar un caso disciplinario o de convivencia de un estudiante.
    Los campos están en el parcial incidencias._form, que necesita las variables $estudiantes,
    $categorias y $estados enviadas por el controlador.
    La usa el personal con permiso para gestionar incidencias (dirección, regencia, etc.).
--}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva incidencia</h2></x-slot>
    <div class="py-8"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8"><div class="bg-white shadow-sm rounded-lg p-6">
        {{-- Formulario de registro: se envía a incidencias.store con el token CSRF y los campos del parcial compartido. --}}
        <form method="POST" action="{{ route('incidencias.store') }}" class="space-y-4">@csrf @include('incidencias._form') <x-primary-button>Guardar</x-primary-button></form>
    </div></div></div>
</x-app-layout>
