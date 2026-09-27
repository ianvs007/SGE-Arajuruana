<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Incidencias</h2>
            <div class="flex gap-2">
                <a href="{{ route('incidencias.categorias') }}"><x-secondary-button type="button">Categorías</x-secondary-button></a>
                <a href="{{ route('incidencias.create') }}"><x-primary-button type="button">Nueva incidencia</x-primary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="mb-4 grid sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <x-input-label for="q" value="Buscar alumno" />
                        <x-text-input id="q" name="q" class="block mt-1 w-full" :value="request('q')" placeholder="Nombre o código" />
                    </div>
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todos</option>
                            @foreach ($estados as $value => $label)
                                <option value="{{ $value }}" @selected((string) request('estado') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="categoria_id" value="Categoría" />
                        <select id="categoria_id" name="categoria_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todas</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}" @selected((string) request('categoria_id') === (string) $categoria->id)>{{ $categoria->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="solo_confidenciales" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm" @checked(request()->boolean('solo_confidenciales'))>
                            Solo confidenciales
                        </label>
                        <x-primary-button>Filtrar</x-primary-button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Fecha</th>
                                <th class="pr-3">Estudiante</th>
                                <th class="pr-3">Categoría</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Registrado por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($incidencias as $incidencia)
                                <tr class="border-b border-slate-100 {{ $incidencia->confidencial ? 'bg-violet-50/60' : '' }}">
                                    <td class="py-2.5 pr-3">{{ optional($incidencia->fecha)->format('d/m/Y') }}</td>
                                    <td class="pr-3">
                                        {{ $incidencia->estudiante?->nombreCompleto() }}
                                        @if ($incidencia->confidencial)
                                            <span class="ml-1 text-[11px] bg-violet-100 text-violet-800 rounded px-1.5 py-0.5">Confidencial</span>
                                        @endif
                                    </td>
                                    <td class="pr-3">{{ $incidencia->categoria?->nombre ?? $incidencia->tipo }}</td>
                                    <td class="pr-3">
                                        @php($colores = ['abierta' => 'bg-rose-100 text-rose-800', 'en_seguimiento' => 'bg-amber-100 text-amber-800', 'cerrada' => 'bg-emerald-100 text-emerald-800'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$incidencia->estado_seguimiento] ?? '' }}">{{ $incidencia->nombreEstado() }}</span>
                                    </td>
                                    <td class="pr-3">{{ $incidencia->registrador?->name ?? '—' }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <a href="{{ route('incidencias.edit', $incidencia) }}" class="text-sky-700 hover:underline">Ver / Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">No hay incidencias para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $incidencias->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
