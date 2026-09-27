<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ $estudiante->nombreCompleto() }}</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('historial.show', $estudiante) }}"><x-primary-button type="button">Ver historial</x-primary-button></a>
                @can('estudiantes.gestionar')
                    <a href="{{ route('estudiantes.edit', $estudiante) }}"><x-secondary-button type="button">Editar</x-secondary-button></a>
                @endcan
                <a href="{{ route('estudiantes.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-4">Datos generales</h3>
                <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Código</dt>
                        <dd class="font-medium">{{ $estudiante->codigo }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Documento</dt>
                        <dd class="font-medium">{{ $estudiante->documento ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Curso</dt>
                        <dd class="font-medium">{{ $estudiante->curso?->etiqueta() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Fecha de nacimiento</dt>
                        <dd class="font-medium">{{ optional($estudiante->fecha_nacimiento)->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Sexo</dt>
                        <dd class="font-medium">{{ $estudiante->sexo ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Estado</dt>
                        <dd class="font-medium">{{ ucfirst($estudiante->estado) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Padres</dt>
                        <dd class="font-medium">{{ $estudiante->padres->pluck('name')->join(', ') ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-slate-500">Observaciones</dt>
                        <dd class="font-medium">{{ $estudiante->observaciones ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Últimas asistencias</h3>
                    <ul class="text-sm space-y-2">
                        @forelse ($estudiante->asistencias as $item)
                            <li class="flex justify-between border-b border-slate-100 pb-2">
                                <span>{{ optional($item->fecha)->format('d/m/Y') }}</span>
                                <span>{{ ucfirst($item->estado) }}</span>
                            </li>
                        @empty
                            <li class="text-slate-500">Sin registros.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Últimas salidas</h3>
                    <ul class="text-sm space-y-2">
                        @forelse ($estudiante->salidas as $item)
                            <li class="flex justify-between border-b border-slate-100 pb-2">
                                <span>{{ optional($item->fecha)->format('d/m/Y') }}</span>
                                <span>{{ ucfirst($item->motivo) }}</span>
                            </li>
                        @empty
                            <li class="text-slate-500">Sin registros.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Últimas incidencias</h3>
                    <ul class="text-sm space-y-2">
                        @forelse ($estudiante->incidencias as $item)
                            <li class="border-b border-slate-100 pb-2">
                                <div class="flex justify-between">
                                    <span>{{ optional($item->fecha)->format('d/m/Y') }}</span>
                                    <span>{{ $item->estado_seguimiento }}</span>
                                </div>
                                <div class="text-slate-600">{{ $item->tipo }}</div>
                            </li>
                        @empty
                            <li class="text-slate-500">Sin registros.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Últimas citaciones</h3>
                    <ul class="text-sm space-y-2">
                        @forelse ($estudiante->citaciones as $item)
                            <li class="border-b border-slate-100 pb-2">
                                <div class="flex justify-between">
                                    <span>{{ optional($item->fecha)->format('d/m/Y') }}</span>
                                    <span>{{ ucfirst(str_replace('_', ' ', $item->estado)) }}</span>
                                </div>
                                <div class="text-slate-600">{{ $item->motivo }}</div>
                            </li>
                        @empty
                            <li class="text-slate-500">Sin registros.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
