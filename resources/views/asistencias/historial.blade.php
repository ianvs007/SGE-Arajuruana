{{--
    Vista: Historial de asistencia
    Permite consultar los registros de asistencia de uno o de todos los
    estudiantes dentro de un rango de fechas. Es una consulta de solo lectura.

    Variables que recibe del controlador:
    - $asistencias: registros paginados.
    - $estudiantes: lista de estudiantes para el filtro.
    - $estudianteId, $desde y $hasta: valores actuales de los filtros.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Historial de asistencia</h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Filtros: estudiante y rango de fechas --}}
                <form method="GET" class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <select name="estudiante_id" class="border-gray-300 rounded-md shadow-sm w-full">
                        <option value="">Todos</option>
                        @foreach ($estudiantes as $est)
                            <option value="{{ $est->id }}" @selected($estudianteId == $est->id)>{{ $est->nombreCompleto() }}</option>
                        @endforeach
                    </select>
                    <x-text-input type="date" name="desde" :value="$desde" />
                    <x-text-input type="date" name="hasta" :value="$hasta" />
                    <x-primary-button>Filtrar</x-primary-button>
                </form>
                {{-- Tabla con fecha, estudiante, estado y quién hizo el registro --}}
                <div class="overflow-x-auto -mx-6 px-6 sm:mx-0 sm:px-0">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left border-b"><th class="py-2">Fecha</th><th>Estudiante</th><th>Estado</th><th>Registrado por</th></tr></thead>
                    <tbody>
                        @forelse ($asistencias as $a)
                            <tr class="border-b">
                                <td class="py-2">{{ $a->fecha->format('d/m/Y') }}</td>
                                <td>{{ $a->estudiante?->nombreCompleto() }}</td>
                                <td>{{ $a->estado }}</td>
                                <td>{{ $a->registrador?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin registros de asistencia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                {{-- Enlaces de paginación --}}
                <div class="mt-4">{{ $asistencias->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
