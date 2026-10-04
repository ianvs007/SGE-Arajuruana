{{--
    Vista: Listado de asistencias
    Muestra los registros de asistencia de un día, filtrando por curso y turno.
    La usan la dirección y los docentes para revisar la asistencia, y también el
    responsable familiar para verificar la de sus hijos (el controlador ya le
    filtra solo los registros que le corresponden).

    Variables que recibe del controlador:
    - $asistencias: registros paginados.
    - $cursos: cursos disponibles para el filtro.
    - $fecha, $cursoId y $turno: valores actuales de los filtros.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Asistencias</h2>
            <div class="flex gap-2">
                {{--
                    Los botones de reporte y de registro solo aparecen para quien gestiona asistencia
                    (dirección y docentes). El reporte por curso es institucional, por eso el
                    responsable familiar no lo ve y revisa a sus hijos en este listado diario.
                --}}
                @can('asistencia.gestionar')
                    <a href="{{ route('asistencias.reporte', ['curso_id' => $cursoId, 'turno' => $turno, 'desde' => $fecha, 'hasta' => $fecha]) }}"><x-secondary-button type="button">Reporte con denominador</x-secondary-button></a>
                    <a href="{{ route('asistencias.create', ['fecha' => $fecha, 'curso_id' => $cursoId, 'turno' => $turno]) }}"><x-primary-button type="button">Registrar asistencia</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Filtros de búsqueda: fecha, curso y turno --}}
                <form method="GET" class="mb-4 grid sm:grid-cols-4 gap-3">
                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-text-input id="fecha" type="date" name="fecha" class="block mt-1 w-full" :value="$fecha" />
                    </div>
                    <div>
                        <x-input-label for="curso_id" value="Curso" />
                        <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todos</option>
                            @foreach ($cursos as $curso)
                                <option value="{{ $curso->id }}" @selected((string) $cursoId === (string) $curso->id)>{{ $curso->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="turno" value="Turno" />
                        <select id="turno" name="turno" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Ambos</option>
                            @foreach (\App\Models\Asistencia::TURNOS as $value => $label)
                                <option value="{{ $value }}" @selected((string) $turno === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <x-primary-button class="w-full sm:w-auto">Filtrar</x-primary-button>
                    </div>
                </form>

                {{--
                    Tabla de registros. Cada estado tiene su color y en la última columna se ve quién
                    registró la asistencia y, si alguien la corrigió después, quién lo hizo.
                    Si no hay registros se aclara que "sin registro" no significa que el alumno faltó.
                --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Fecha</th>
                                <th class="pr-3">Turno</th>
                                <th class="pr-3">Estudiante</th>
                                <th class="pr-3">Curso</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Observación</th>
                                <th class="pr-3">Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($asistencias as $asistencia)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">{{ optional($asistencia->fecha)->format('d/m/Y') }}</td>
                                    <td class="pr-3">{{ $asistencia->nombreTurno() }}</td>
                                    <td class="pr-3">{{ $asistencia->estudiante?->nombreCompleto() }}</td>
                                    <td class="pr-3">{{ $asistencia->curso?->etiqueta() ?? '—' }}</td>
                                    <td class="pr-3">
                                        @php($colores = ['presente' => 'bg-emerald-100 text-emerald-800', 'ausente' => 'bg-rose-100 text-rose-800', 'atrasado' => 'bg-amber-100 text-amber-800', 'justificada' => 'bg-sky-100 text-sky-800'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$asistencia->estado] ?? '' }}">{{ $asistencia->nombreEstado() }}</span>
                                    </td>
                                    <td class="pr-3">{{ $asistencia->observacion ?? '—' }}</td>
                                    <td class="pr-3 text-xs text-slate-500">
                                        {{ $asistencia->registrador?->name ?? '—' }}
                                        @if ($asistencia->modificador)
                                            <div class="text-amber-700">Corregido por {{ $asistencia->modificador->name }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-500">No hay asistencias para los filtros seleccionados. Recuerde: sin registro no equivale a ausente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Enlaces de paginación --}}
                <div class="mt-4">{{ $asistencias->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
