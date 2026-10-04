{{--
    Vista: Reporte de asistencia por curso
    Resume la asistencia de un curso en un rango de fechas, alumno por alumno.
    Lo importante de este reporte es que el porcentaje se calcula solo sobre los
    días en que realmente hubo clases (el "denominador"), así los feriados o días
    sin horario no perjudican al estudiante. Lo usan la dirección y los docentes.

    Variables que recibe del controlador:
    - $cursos: cursos para el selector; $curso: el curso consultado.
    - $turno, $desde, $hasta: filtros aplicados.
    - $totales: conteos generales (días con clases, presentes, ausentes, etc.).
    - $filas: una fila por estudiante con sus conteos y su porcentaje.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Reporte de asistencia — denominador explícito (§9)</h2>
            <a href="{{ route('asistencias.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{-- Filtros del reporte: curso, turno y rango de fechas (todos obligatorios) --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" action="{{ route('asistencias.reporte') }}" class="grid sm:grid-cols-5 gap-3 items-end">
                    <div>
                        <x-input-label for="curso_id" value="Curso" />
                        <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                            @foreach ($cursos as $cursoItem)
                                <option value="{{ $cursoItem->id }}" @selected((string) request('curso_id') === (string) $cursoItem->id)>{{ $cursoItem->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="turno" value="Turno" />
                        <select id="turno" name="turno" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                            @foreach (\App\Models\Asistencia::TURNOS as $value => $label)
                                <option value="{{ $value }}" @selected($turno === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="desde" value="Desde" />
                        <x-text-input id="desde" type="date" name="desde" class="block mt-1 w-full" :value="$desde" required />
                    </div>
                    <div>
                        <x-input-label for="hasta" value="Hasta" />
                        <x-text-input id="hasta" type="date" name="hasta" class="block mt-1 w-full" :value="$hasta" required />
                    </div>
                    <x-primary-button>Generar</x-primary-button>
                </form>
            </div>

            {{-- Resultado del reporte: datos del curso y periodo consultado --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="text-sm text-slate-600 mb-4">
                    <p class="font-semibold text-slate-800">{{ $curso->etiqueta() }} — Turno {{ \App\Models\Asistencia::TURNOS[$turno] }}</p>
                    <p>Período: {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}</p>
                </div>

                {{--
                    Tarjetas con los totales del curso. La primera es el denominador (días con clases).
                    "Sin registro" se muestra aparte porque no tomar lista no es lo mismo que faltar.
                --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4 text-center">
                    <div class="bg-slate-50 rounded p-3">
                        <div class="text-xl font-bold text-slate-800">{{ $totales['dias_habiles'] }}</div>
                        <div class="text-xs text-slate-500">Días con clases (denominador)</div>
                    </div>
                    <div class="bg-emerald-50 rounded p-3">
                        <div class="text-xl font-bold text-emerald-700">{{ $totales['presente'] }}</div>
                        <div class="text-xs text-emerald-600">Presentes</div>
                    </div>
                    <div class="bg-amber-50 rounded p-3">
                        <div class="text-xl font-bold text-amber-700">{{ $totales['atrasado'] }}</div>
                        <div class="text-xs text-amber-600">Atrasados</div>
                    </div>
                    <div class="bg-sky-50 rounded p-3">
                        <div class="text-xl font-bold text-sky-700">{{ $totales['justificada'] }}</div>
                        <div class="text-xs text-sky-600">Justificadas</div>
                    </div>
                    <div class="bg-rose-50 rounded p-3">
                        <div class="text-xl font-bold text-rose-700">{{ $totales['ausente'] }}</div>
                        <div class="text-xs text-rose-600">Ausencias</div>
                    </div>
                    <div class="bg-slate-100 rounded p-3">
                        <div class="text-xl font-bold text-slate-600">{{ $totales['sin_registro'] }}</div>
                        <div class="text-xs text-slate-500">Sin registro (≠ ausente)</div>
                    </div>
                </div>

                {{-- Detalle por estudiante; si no se puede calcular el porcentaje se muestra un guion --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-left">
                            <tr>
                                <th class="py-2 px-3">Estudiante</th>
                                <th class="px-3 text-center">Presente</th>
                                <th class="px-3 text-center">Atrasado</th>
                                <th class="px-3 text-center">Justificada</th>
                                <th class="px-3 text-center">Ausente</th>
                                <th class="px-3 text-center">Sin registro</th>
                                <th class="px-3 text-center">Asistencia %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr class="border-t border-slate-100">
                                    <td class="py-2 px-3 font-medium text-slate-800">{{ $fila['estudiante']?->nombreCompleto() }}</td>
                                    <td class="px-3 text-center">{{ $fila['conteo']['presente'] }}</td>
                                    <td class="px-3 text-center">{{ $fila['conteo']['atrasado'] }}</td>
                                    <td class="px-3 text-center">{{ $fila['conteo']['justificada'] }}</td>
                                    <td class="px-3 text-center">{{ $fila['conteo']['ausente'] }}</td>
                                    <td class="px-3 text-center text-slate-500">{{ $fila['sin_registro'] }}</td>
                                    <td class="px-3 text-center font-semibold">
                                        {{ $fila['porcentaje_asistencia'] !== null ? $fila['porcentaje_asistencia'].'%' : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-500">
                                        No hay inscripciones activas en este curso.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Nota que explica cómo se calcula el porcentaje, para que el reporte se interprete bien --}}
                <p class="mt-4 text-xs text-slate-500">
                    El porcentaje usa como denominador únicamente los <strong>días con clases</strong> del período
                    (jornadas sin clases y feriados del calendario no cuentan). Presente + atrasado + justificada +
                    ausente + sin registro = días con clases por alumno.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
