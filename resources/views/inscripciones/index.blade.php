<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Inscripciones</h2>
            @can('inscripciones.gestionar')
                <a href="{{ route('inscripciones.create', ['gestion_id' => $gestion->id]) }}"><x-primary-button type="button">Nueva inscripción</x-primary-button></a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="mb-4 grid sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <x-input-label for="gestion_id" value="Gestión" />
                        <select id="gestion_id" name="gestion_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" onchange="this.form.submit()">
                            @foreach ($gestiones as $g)
                                <option value="{{ $g->id }}" @selected($g->id === $gestion->id)>{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="curso_id" value="Curso" />
                        <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todos</option>
                            @foreach ($cursos as $curso)
                                <option value="{{ $curso->id }}" @selected((string) request('curso_id') === (string) $curso->id)>{{ $curso->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="q" value="Buscar alumno" />
                        <x-text-input id="q" name="q" class="block mt-1 w-full" :value="request('q')" placeholder="Nombre, código o documento" />
                    </div>
                    <x-primary-button>Filtrar</x-primary-button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-left">
                            <tr>
                                <th class="py-3 px-4">Alumno</th>
                                <th class="px-4">Curso</th>
                                <th class="px-4">Fecha inscripción</th>
                                <th class="px-4">Estado</th>
                                <th class="px-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inscripciones as $inscripcion)
                                <tr class="border-t border-slate-100">
                                    <td class="py-3 px-4 font-medium text-slate-800">
                                        {{ $inscripcion->estudiante?->nombreCompleto() }}
                                        <div class="text-xs text-slate-400">{{ $inscripcion->estudiante?->codigo }}</div>
                                    </td>
                                    <td class="px-4">{{ $inscripcion->curso?->etiqueta() }}</td>
                                    <td class="px-4">{{ optional($inscripcion->fecha_inscripcion)->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4">
                                        @php($colores = ['activa' => 'bg-emerald-100 text-emerald-800', 'retirada' => 'bg-amber-100 text-amber-800', 'trasladada' => 'bg-sky-100 text-sky-800', 'cancelada' => 'bg-slate-100 text-slate-600'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$inscripcion->estado] ?? 'bg-slate-100' }}">{{ ucfirst($inscripcion->estado) }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap space-x-3">
                                        @can('inscripciones.gestionar')
                                            <a class="text-sky-700 hover:underline" href="{{ route('inscripciones.edit', $inscripcion) }}">Editar</a>
                                            @if ($inscripcion->estado === 'activa')
                                                <form method="POST" action="{{ route('inscripciones.destroy', $inscripcion) }}" class="inline"
                                                    onsubmit="return confirm('¿Cancelar esta inscripción? El historial se conserva.')">
                                                    @csrf @method('DELETE')
                                                    <button class="text-rose-600 hover:underline">Cancelar</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-500">
                                        No hay inscripciones para esta gestión con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $inscripciones->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
