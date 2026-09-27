<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Citaciones</h2>
            <div class="flex items-center gap-3">
                @if ($pendientesRevision > 0)
                    <span class="text-sm bg-rose-100 text-rose-800 rounded-full px-3 py-1">{{ $pendientesRevision }} revisión(es) vencida(s)</span>
                @endif
                @can('citaciones.gestionar')
                    <a href="{{ route('citaciones.create') }}"><x-primary-button type="button">Nueva citación</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="mb-4 grid sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todos</option>
                            @foreach ($estados as $value => $label)
                                <option value="{{ $value }}" @selected((string) request('estado') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center pb-1">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="revision_vencida" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm" @checked(request()->boolean('revision_vencida'))>
                            Solo con revisión vencida
                        </label>
                    </div>
                    <x-primary-button>Filtrar</x-primary-button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Fecha / Hora</th>
                                <th class="pr-3">Estudiante</th>
                                <th class="pr-3">Convocado</th>
                                <th class="pr-3">Motivo</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Revisión</th>
                                <th class="pr-3">Emisor</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($citaciones as $citacion)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">
                                        {{ optional($citacion->fecha)->format('d/m/Y') }}
                                        <span class="text-slate-400">{{ \Illuminate\Support\Str::of($citacion->hora)->substr(0, 5) }}</span>
                                    </td>
                                    <td class="pr-3">{{ $citacion->estudiante?->nombreCompleto() }}</td>
                                    <td class="pr-3">{{ $citacion->padre?->name ?? '—' }}</td>
                                    <td class="pr-3">{{ $citacion->motivo }}</td>
                                    <td class="pr-3">
                                        @php($colores = ['pendiente' => 'bg-amber-100 text-amber-800', 'atendida' => 'bg-emerald-100 text-emerald-800', 'no_asistio' => 'bg-rose-100 text-rose-800', 'en_seguimiento' => 'bg-sky-100 text-sky-800', 'cerrada' => 'bg-slate-100 text-slate-600', 'cancelada' => 'bg-slate-100 text-slate-600'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$citacion->estado] ?? '' }}">{{ $citacion->nombreEstado() }}</span>
                                    </td>
                                    <td class="pr-3">
                                        @if ($citacion->fecha_revision)
                                            @if ($citacion->requiereRevision())
                                                <span class="text-xs font-semibold text-rose-700">{{ $citacion->fecha_revision->format('d/m/Y') }} ⚠</span>
                                            @else
                                                <span class="text-xs text-slate-500">{{ $citacion->fecha_revision->format('d/m/Y') }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="pr-3">{{ $citacion->generador?->name ?? '—' }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <a href="{{ route('citaciones.show', $citacion) }}" class="text-sky-700 hover:underline">Ver</a>
                                        @can('citaciones.gestionar')
                                            · <a href="{{ route('citaciones.edit', $citacion) }}" class="text-sky-700 hover:underline">Editar</a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-slate-500">No hay citaciones para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $citaciones->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
