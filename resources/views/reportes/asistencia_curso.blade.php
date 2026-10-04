{{--
    Vista: Reporte de asistencia por curso (en pantalla).
    Es el reporte oficial de asistencia. Para un curso, un turno y un rango de fechas, muestra
    por alumno cuántos días estuvo presente, atrasado, con falta justificada, ausente o sin
    registro, y su porcentaje de asistencia. También permite descargarlo en PDF o Excel.
    Recibe del controlador:
      - $cursos: cursos que el usuario puede consultar (un docente solo ve los suyos).
      - $filtro: los valores elegidos (curso_id, turno, desde, hasta).
      - $data: el resultado del cálculo (curso, turno, rango, filas por alumno y totales), o
        null si no hay cursos disponibles.
    Lo usa el personal con permiso para gestionar asistencia.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Asistencia por curso</h2>
            <a href="{{ route('reportes.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            {{-- Filtros del reporte: curso, turno y rango de fechas (por defecto, desde el inicio del mes hasta hoy). --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" action="{{ route('reportes.asistencia-curso') }}" class="grid sm:grid-cols-5 gap-3 items-end">
                    <div class="sm:col-span-2">
                        <x-input-label for="curso_id" value="Curso" />
                        <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            @foreach ($cursos as $curso)
                                <option value="{{ $curso->id }}" @selected((string) ($filtro['curso_id'] ?? '') === (string) $curso->id)>{{ $curso->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="turno" value="Turno" />
                        <select id="turno" name="turno" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="manana" @selected(($filtro['turno'] ?? '') === 'manana')>Mañana</option>
                            <option value="tarde" @selected(($filtro['turno'] ?? '') === 'tarde')>Tarde</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="desde" value="Desde" />
                        <x-text-input type="date" id="desde" name="desde" class="block mt-1 w-full" :value="$filtro['desde'] ?? now()->startOfMonth()->toDateString()" required />
                    </div>
                    <div>
                        <x-input-label for="hasta" value="Hasta" />
                        <x-text-input type="date" id="hasta" name="hasta" class="block mt-1 w-full" :value="$filtro['hasta'] ?? now()->toDateString()" required />
                    </div>
                    {{-- Los botones de descarga solo aparecen cuando hay datos; reciben los mismos filtros de la pantalla. --}}
                    <div class="sm:col-span-5 flex flex-wrap gap-2">
                        <x-primary-button>Ver reporte</x-primary-button>
                        @if ($data)
                            <a href="{{ route('reportes.asistencia-curso.pdf', $filtro) }}" target="_blank">
                                <x-secondary-button type="button">Descargar PDF</x-secondary-button>
                            </a>
                            <a href="{{ route('reportes.asistencia-curso.excel', $filtro) }}">
                                <x-secondary-button type="button">Descargar Excel</x-secondary-button>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Resultado del reporte, o un mensaje si el usuario no tiene cursos para consultar. --}}
            @if ($data)
                <div class="bg-white shadow-sm rounded-lg p-6 overflow-x-auto">
                    {{--
                        Datos generales del reporte. Los días hábiles del rango se usan como denominador
                        para calcular el porcentaje de asistencia de cada alumno.
                    --}}
                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600 mb-4">
                        <span><strong>Curso:</strong> {{ $data['curso']->etiqueta() }}</span>
                        <span><strong>Turno:</strong> {{ \App\Models\Curso::TURNOS[$data['turno']] ?? $data['turno'] }}</span>
                        <span><strong>Rango:</strong> {{ \Illuminate\Support\Carbon::parse($data['desde'])->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($data['hasta'])->format('d/m/Y') }}</span>
                        <span><strong>Días hábiles (denominador):</strong> {{ $data['totales']['dias_habiles'] }}</span>
                    </div>

                    {{-- Tabla con el conteo de cada estado de asistencia por alumno. --}}
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                                <th class="py-2 pr-3">Código</th>
                                <th class="py-2 pr-3">Alumno</th>
                                <th class="py-2 pr-3 text-right">Días hábiles</th>
                                <th class="py-2 pr-3 text-right">Presente</th>
                                <th class="py-2 pr-3 text-right">Atrasado</th>
                                <th class="py-2 pr-3 text-right">Justificada</th>
                                <th class="py-2 pr-3 text-right">Ausente</th>
                                <th class="py-2 pr-3 text-right">Sin registro</th>
                                <th class="py-2 text-right">Asistencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['filas'] as $fila)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2 pr-3">{{ $fila['estudiante']?->codigo }}</td>
                                    <td class="py-2 pr-3 font-medium">{{ $fila['estudiante']?->nombreCompleto() }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $fila['dias_habiles'] }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $fila['conteo']['presente'] }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $fila['conteo']['atrasado'] }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $fila['conteo']['justificada'] }}</td>
                                    <td class="py-2 pr-3 text-right text-rose-700">{{ $fila['conteo']['ausente'] }}</td>
                                    <td class="py-2 pr-3 text-right text-slate-400">{{ $fila['sin_registro'] }}</td>
                                    {{-- Porcentaje con un decimal y formato boliviano (coma decimal); si no se puede calcular se muestra un guion. --}}
                                    <td class="py-2 text-right font-semibold">
                                        {{ $fila['porcentaje_asistencia'] !== null ? number_format($fila['porcentaje_asistencia'], 1, ',', '.').'%' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        {{-- Totales del curso. --}}
                        <tfoot>
                            <tr class="font-bold bg-slate-50">
                                <td class="py-2 pr-3" colspan="2">TOTALES ({{ $data['totales']['alumnos'] }} alumnos)</td>
                                <td class="py-2 pr-3 text-right">{{ $data['totales']['dias_habiles'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $data['totales']['presente'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $data['totales']['atrasado'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $data['totales']['justificada'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $data['totales']['ausente'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $data['totales']['sin_registro'] }}</td>
                                <td class="py-2"></td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Nota aclaratoria: "sin registro" no se cuenta como falta. --}}
                    <p class="text-xs text-slate-500 mt-4">
                        Días hábiles = denominador explícito del rango (§9). «Sin registro» no equivale a ausencia.
                        PDF y Excel usan estos mismos datos: totales idénticos (§16).
                    </p>
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6 text-center text-slate-500">
                    No hay cursos visibles para reportar en la gestión actual.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
