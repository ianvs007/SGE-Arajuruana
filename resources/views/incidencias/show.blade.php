{{--
    Vista: Detalle de una incidencia.
    Muestra en modo lectura todos los datos de un caso disciplinario: estudiante, fecha, tipo,
    estado de seguimiento, descripción, medida aplicada, observaciones y quién lo registró.
    Recibe del controlador $incidencia. Desde aquí se accede a la edición para darle seguimiento.
--}}
<x-app-layout>
    {{-- Encabezado con el enlace para editar o registrar el seguimiento del caso. --}}
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Incidencia</h2>
            <a href="{{ route('incidencias.edit', $incidencia) }}" class="text-indigo-600 text-sm">Editar / seguimiento</a>
        </div>
    </x-slot>
    {{-- Ficha del caso. Los campos opcionales vacíos se muestran con un guion. --}}
    <div class="py-8"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8"><div class="bg-white shadow-sm rounded-lg p-6 text-sm space-y-2">
        <div><span class="text-gray-500">Estudiante:</span> {{ $incidencia->estudiante?->nombreCompleto() }}</div>
        <div><span class="text-gray-500">Fecha:</span> {{ $incidencia->fecha->format('d/m/Y') }}</div>
        <div><span class="text-gray-500">Tipo:</span> {{ $incidencia->tipo }}</div>
        <div><span class="text-gray-500">Seguimiento:</span> {{ $incidencia->estado_seguimiento }}</div>
        <div><span class="text-gray-500">Descripción:</span> {{ $incidencia->descripcion }}</div>
        <div><span class="text-gray-500">Medida:</span> {{ $incidencia->medida_accion ?: '—' }}</div>
        <div><span class="text-gray-500">Observaciones:</span> {{ $incidencia->observaciones ?: '—' }}</div>
        <div><span class="text-gray-500">Registrado por:</span> {{ $incidencia->registrador?->name }}</div>
    </div></div></div>
</x-app-layout>
