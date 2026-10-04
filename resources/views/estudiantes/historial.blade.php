{{--
    Vista: Historial del estudiante
    Muestra en forma de línea de tiempo todos los eventos de un estudiante
    (asistencias, salidas, incidencias, citaciones, etc.), ordenados por fecha.
    La pueden consultar el personal de la unidad educativa y el responsable
    familiar para el caso de sus propios hijos.

    Variables que recibe del controlador:
    - $estudiante: el estudiante consultado.
    - $eventos: colección de eventos ya unificados por el controlador; cada uno
      trae su fecha, tipo y detalle.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Historial — {{ $estudiante->nombreCompleto() }}</h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Línea de tiempo: cada evento lleva un borde a la izquierda con la fecha, el tipo y el detalle --}}
                <ul class="space-y-3 text-sm">
                    @forelse ($eventos as $evento)
                        <li class="border-l-4 border-indigo-300 pl-3">
                            <div class="text-xs text-gray-500">{{ optional($evento['fecha'])->format('d/m/Y') }} · {{ $evento['tipo'] }}</div>
                            <div>{{ $evento['detalle'] }}</div>
                        </li>
                    @empty
                        <li class="text-gray-500">Sin eventos registrados.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
