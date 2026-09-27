<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Estudiantes</h2>
            @can('estudiantes.gestionar')
                <a href="{{ route('estudiantes.create') }}"><x-primary-button type="button">Nuevo estudiante</x-primary-button></a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="mb-4 flex flex-col sm:flex-row gap-2">
                    <x-text-input name="q" value="{{ $q }}" class="block w-full" placeholder="Buscar por nombre, código o documento" />
                    <x-primary-button>Buscar</x-primary-button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Código</th>
                                <th class="pr-3">Estudiante</th>
                                <th class="pr-3">Curso</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Padres</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($estudiantes as $estudiante)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3 font-medium">{{ $estudiante->codigo }}</td>
                                    <td class="pr-3">{{ $estudiante->nombreCompleto() }}</td>
                                    <td class="pr-3">{{ $estudiante->curso?->etiqueta() ?? '—' }}</td>
                                    <td class="pr-3">{{ ucfirst($estudiante->estado) }}</td>
                                    <td class="pr-3">{{ $estudiante->padres->pluck('name')->join(', ') ?: '—' }}</td>
                                    <td class="text-right whitespace-nowrap space-x-3">
                                        <a href="{{ route('estudiantes.show', $estudiante) }}" class="text-sky-700 hover:underline">Ver</a>
                                        <a href="{{ route('historial.show', $estudiante) }}" class="text-sky-700 hover:underline">Historial</a>
                                        @can('estudiantes.gestionar')
                                            <a href="{{ route('estudiantes.edit', $estudiante) }}" class="text-sky-700 hover:underline">Editar</a>
                                            <form action="{{ route('estudiantes.destroy', $estudiante) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar estudiante?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">No hay estudiantes registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $estudiantes->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
