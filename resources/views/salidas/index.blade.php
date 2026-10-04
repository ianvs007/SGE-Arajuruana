{{--
    Vista: Listado de salidas autorizadas.
    Muestra las salidas de alumnos durante el horario de clases con su estado: autorizada,
    salida efectiva (el alumno ya se fue), retornada o cancelada. Se puede filtrar por estado
    y por fecha.
    Recibe del controlador:
      - $salidas: colección paginada de salidas.
      - $estados: lista de estados (valor => etiqueta) para el filtro.
      - $filtroEstado y $filtroFecha: filtros elegidos actualmente.
      - $abiertasHoy: cantidad de salidas de hoy que todavía no se cerraron.
    La usa el personal con el permiso salidas.ver; autorizar requiere salidas.autorizar.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Salidas autorizadas</h2>
            <div class="flex items-center gap-3">
                {{-- Indicador de salidas abiertas hoy, para que Administración vea de un vistazo cuántas faltan cerrar. --}}
                @if ($abiertasHoy > 0)
                    <span class="text-sm bg-amber-100 text-amber-800 rounded-full px-3 py-1">{{ $abiertasHoy }} abierta(s) hoy</span>
                @endif
                {{-- El botón para autorizar una nueva salida solo aparece para quien tiene ese permiso. --}}
                @can('salidas.autorizar')
                    <a href="{{ route('salidas.create') }}"><x-primary-button type="button">Autorizar salida</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Filtros por estado y fecha, enviados por GET. --}}
                <form method="GET" class="mb-4 grid sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todos</option>
                            @foreach ($estados as $value => $label)
                                <option value="{{ $value }}" @selected((string) $filtroEstado === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-text-input id="fecha" type="date" name="fecha" class="block mt-1 w-full" :value="$filtroFecha" />
                    </div>
                    <x-primary-button>Filtrar</x-primary-button>
                </form>

                {{-- Tabla de salidas. --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Fecha</th>
                                <th class="pr-3">Estudiante</th>
                                <th class="pr-3">Motivo</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Autorizó</th>
                                <th class="pr-3">Hora salida</th>
                                <th class="pr-3">Hora retorno</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($salidas as $salida)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">{{ optional($salida->fecha)->format('d/m/Y') }}</td>
                                    <td class="pr-3">{{ $salida->estudiante?->nombreCompleto() }}</td>
                                    <td class="pr-3">{{ $salida->nombreMotivo() }}</td>
                                    <td class="pr-3">
                                        {{-- Color de cada estado: ámbar autorizada, celeste salida efectiva, verde retornada y gris cancelada. --}}
                                        @php($colores = ['autorizada' => 'bg-amber-100 text-amber-800', 'salida_efectiva' => 'bg-sky-100 text-sky-800', 'retornada' => 'bg-emerald-100 text-emerald-800', 'cancelada' => 'bg-slate-100 text-slate-600'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$salida->estado] ?? '' }}">{{ $salida->nombreEstado() }}</span>
                                    </td>
                                    <td class="pr-3">{{ $salida->autorizante?->name ?? '—' }}</td>
                                    {{-- Las horas se muestran sin segundos; si aún no se registraron, aparece un guion. --}}
                                    <td class="pr-3">{{ $salida->hora_salida ? \Illuminate\Support\Str::of($salida->hora_salida)->substr(0, 5) : '—' }}</td>
                                    <td class="pr-3">{{ $salida->hora_retorno ? \Illuminate\Support\Str::of($salida->hora_retorno)->substr(0, 5) : '—' }}</td>
                                    <td class="pr-3 text-right whitespace-nowrap">
                                        <a class="text-sky-700 hover:underline" href="{{ route('salidas.show', $salida) }}">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-slate-500">No hay salidas registradas para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Paginación. --}}
                <div class="mt-4">{{ $salidas->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
