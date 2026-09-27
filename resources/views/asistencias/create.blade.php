<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Registrar asistencia</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" action="{{ route('asistencias.create') }}" class="grid sm:grid-cols-4 gap-3">
                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-text-input id="fecha" type="date" name="fecha" class="block mt-1 w-full" :value="$fecha" required />
                    </div>
                    <div>
                        <x-input-label for="curso_id" value="Curso" />
                        <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                            <option value="">Seleccione curso</option>
                            @foreach ($cursos as $cursoItem)
                                <option value="{{ $cursoItem->id }}" @selected((string) $cursoId === (string) $cursoItem->id)>{{ $cursoItem->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="turno_filtro" value="Turno" />
                        <select id="turno_filtro" name="turno" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                            @foreach (\App\Models\Asistencia::TURNOS as $value => $label)
                                <option value="{{ $value }}" @selected(($turno ?? 'manana') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <x-primary-button>Cargar lista</x-primary-button>
                    </div>
                </form>
            </div>

            @if ($curso)
                @if (! $hayClases)
                    {{-- §9: sin clases programadas NO se registra asistencia ni se generan ausentes. --}}
                    <div class="bg-sky-50 border border-sky-200 text-sky-800 px-4 py-4 rounded space-y-1">
                        <p class="font-semibold">No hay clases programadas para {{ $curso->etiqueta() }} el {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }} en el turno {{ \App\Models\Asistencia::TURNOS[$turno] ?? $turno }}.</p>
                        @if ($excepcion)
                            <p class="text-sm">Motivo del calendario: <strong>{{ $excepcion->nombreTipo() }}</strong>{{ $excepcion->motivo ? ' — '.$excepcion->motivo : '' }}.</p>
                        @else
                            <p class="text-sm">El curso no tiene horario activo ese día/turno. Esta jornada NO cuenta como ausencia (día no aplicable).</p>
                        @endif
                    </div>
                @elseif ($inscripciones->isEmpty())
                    <div class="bg-white shadow-sm rounded-lg p-6 text-slate-500">
                        No hay alumnos con inscripción activa en este curso para su gestión. Inscriba alumnos primero (menú Inscripciones).
                    </div>
                @else
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <form method="POST" action="{{ route('asistencias.store') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                            <input type="hidden" name="curso_id" value="{{ $curso->id }}">
                            <input type="hidden" name="turno" value="{{ $turno }}">

                            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-500">
                                <p>Curso: <strong class="text-slate-700">{{ $curso->etiqueta() }}</strong></p>
                                <p>Turno: <strong class="text-slate-700">{{ \App\Models\Asistencia::TURNOS[$turno] ?? $turno }}</strong></p>
                                <p>Fecha: <strong class="text-slate-700">{{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}</strong></p>
                                <p>Alumnos: <strong class="text-slate-700">{{ $inscripciones->count() }}</strong></p>
                            </div>

                            @if ($existentes->isNotEmpty())
                                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm">
                                    Ya existen {{ $existentes->count() }} registro(s) para esta fecha/turno. Guardar aplicará
                                    <strong>correcciones con trazabilidad</strong> (quedará registrado quién corrigió).
                                </div>
                            @endif

                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left border-b border-slate-200 text-slate-500">
                                            <th class="py-2 pr-3">Estudiante</th>
                                            <th class="pr-3">Estado</th>
                                            <th class="pr-3">Observación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($inscripciones as $inscripcion)
                                            @php($estudiante = $inscripcion->estudiante)
                                            @continue(! $estudiante)
                                            @php($existente = $existentes->get($estudiante->id))
                                            <tr class="border-b border-slate-100 align-top {{ $existente ? 'bg-amber-50/40' : '' }}">
                                                <td class="py-2.5 pr-3">
                                                    {{ $estudiante->nombreCompleto() }}
                                                    @if ($existente)
                                                        <span class="ml-1 text-[11px] text-amber-600">(ya registrado: {{ $existente->nombreEstado() }})</span>
                                                    @endif
                                                </td>
                                                <td class="pr-3">
                                                    <select name="estados[{{ $estudiante->id }}]" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full" required>
                                                        @foreach (\App\Models\Asistencia::ESTADOS as $value => $label)
                                                            <option value="{{ $value }}" @selected(old("estados.{$estudiante->id}", $existente?->estado ?? 'presente') === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="pr-3">
                                                    <x-text-input name="observaciones[{{ $estudiante->id }}]" class="block w-full" :value="old('observaciones.'.$estudiante->id, $existente?->observacion)" placeholder="Opcional" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex gap-3">
                                <x-primary-button>Guardar asistencia</x-primary-button>
                                <a href="{{ route('asistencias.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                            </div>
                        </form>
                    </div>
                @endif
            @else
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded">
                    Seleccione fecha y curso para cargar la lista de estudiantes.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
