{{--
    Vista: Historial de un estudiante (línea de tiempo).
    Reúne en una sola pantalla todo lo que le ocurrió a un estudiante en la unidad educativa:
    sus inscripciones en cada gestión y los eventos registrados (asistencias, incidencias,
    citaciones, pagos, salidas, etc.), ordenados por fecha.
    Recibe del controlador:
      - $estudiante: el estudiante consultado, con sus responsables (padres o tutores).
      - $inscripciones: sus inscripciones de todas las gestiones.
      - $eventos: arreglo de eventos ya armado, cada uno con 'fecha', 'tipo' y 'detalle'.
      - $verConfidenciales: indica si el rol del usuario puede ver información confidencial.
    La usan el personal docente y administrativo según sus permisos.
--}}
<x-app-layout>
    {{-- Encabezado con el nombre del estudiante y accesos a su ficha y al listado. --}}
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
            {{-- Datos básicos del estudiante: código, curso actual y responsables. --}}
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
                        {{-- Unimos los nombres de los responsables separados por coma; si no tiene ninguno se muestra un guion. --}}
                        <dd class="font-medium">{{ $estudiante->responsables->pluck('name')->join(', ') ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{--
                Inscripciones por gestión. Cada año escolar queda guardado como una inscripción
                aparte, por eso si un estudiante repite curso no se pierde su historial anterior.
            --}}
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

            {{-- Línea de tiempo con todos los eventos del estudiante. --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                    <h3 class="font-semibold text-slate-800">Línea de tiempo</h3>
                    {{--
                        Si el rol del usuario no puede ver datos confidenciales (por ejemplo, ciertas
                        incidencias), el controlador ya los filtró y aquí solo avisamos de esa restricción.
                    --}}
                    @unless ($verConfidenciales)
                        <p class="text-xs text-slate-500">Se muestra únicamente la información autorizada para su rol.</p>
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
                                    {{--
                                        La fecha puede llegar como objeto Carbon o como texto según el módulo
                                        de origen; en ambos casos la mostramos con el formato día/mes/año.
                                    --}}
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
