<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Historial — {{ $estudiante->nombreCompleto() }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('estudiantes.show', $estudiante) }}"><x-secondary-button type="button">Ficha</x-secondary-button></a>
                <a href="{{ route('estudiantes.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6 text-sm">
                <dl class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-slate-500">Código</dt>
                        <dd class="font-medium">{{ $estudiante->codigo }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Curso actual</dt>
                        <dd class="font-medium">{{ $estudiante->cursoActual()?->etiqueta() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Responsables</dt>
                        <dd class="font-medium">{{ $estudiante->responsables->pluck('name')->join(', ') ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- §7: inscripciones por gestión — repetir curso no pierde historial --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-4">Inscripciones por gestión</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Gestión</th>
                                <th class="pr-3">Curso</th>
                                <th class="pr-3">Fecha</th>
                                <th class="pr-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inscripciones as $inscripcion)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">{{ $inscripcion->gestion?->nombre }}</td>
                                    <td class="pr-3">{{ $inscripcion->curso?->etiqueta() }}</td>
                                    <td class="pr-3">{{ $inscripcion->fecha_inscripcion?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="pr-3">{{ ucfirst($inscripcion->estado) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-slate-500">Sin inscripciones registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                    <h3 class="font-semibold text-slate-800">Línea de tiempo</h3>
                    @unless ($verConfidenciales)
                        <p class="text-xs text-slate-500">Se muestra únicamente la información autorizada para su rol (§7).</p>
                    @endunless
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Fecha</th>
                                <th class="pr-3">Tipo</th>
                                <th class="pr-3">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($eventos as $evento)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3 whitespace-nowrap">
                                        {{ $evento['fecha'] instanceof \Illuminate\Support\Carbon ? $evento['fecha']->format('d/m/Y') : \Illuminate\Support\Carbon::parse($evento['fecha'])->format('d/m/Y') }}
                                    </td>
                                    <td class="pr-3">{{ $evento['tipo'] }}</td>
                                    <td class="pr-3">{{ $evento['detalle'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-500">Sin eventos en el historial.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
