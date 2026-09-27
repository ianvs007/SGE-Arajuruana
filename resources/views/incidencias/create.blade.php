<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva incidencia</h2></x-slot>
    <div class="py-8"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8"><div class="bg-white shadow-sm rounded-lg p-6">
        <form method="POST" action="{{ route('incidencias.store') }}" class="space-y-4">@csrf @include('incidencias._form') <x-primary-button>Guardar</x-primary-button></form>
    </div></div></div>
</x-app-layout>
