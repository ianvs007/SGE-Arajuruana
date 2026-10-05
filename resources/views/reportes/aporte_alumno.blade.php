{{--
    Vista: Reporte de aporte por alumno (en pantalla).
    Muestra, para cada alumno, cuánto se le emitió en cuotas del aporte, cuánto pagó, cuánto
    tiene vencido y su saldo pendiente, con la opción de descargar el mismo reporte en PDF o Excel.
    Recibe del controlador:
      - $gestiones y $gestion: lista de gestiones y la gestión seleccionada.
      - $cursos: cursos para el filtro opcional.
      - $filas: un arreglo por alumno (código, nombre, curso, cuotas vencidas, emitido, pagado, vencido, saldo).
      - $totales: las sumas de todas las filas.
      - $hoy: fecha de corte usada para decidir qué cuotas están vencidas.
    Lo usa el personal administrativo con acceso a reportes.
--}}
<x-app-layout>
    {{-- Encabezado con el botón para volver al menú de reportes. --}}
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Aporte por alumno</h2>
            <a href="{{ route('reportes.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            {{--
                Filtros y botones de descarga. Los enlaces de PDF y Excel llevan los mismos filtros
                (gestión y curso) que la pantalla, para que los archivos descargados coincidan con lo que se ve.
            --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" action="{{ route('reportes.aporte-alumno') }}" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <x-input-label for="gestion" value="Gestión" />
                        <select id="gestion" name="gestion" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1">
                            @foreach ($gestiones as $g)
                                <option value="{{ $g->id }}" @selected($gestion && $gestion->id === $g->id)>{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="curso_id" value="Curso (opcional)" />
                        <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1">
                            <option value="">Todos</option>
                            @foreach ($cursos as $curso)
                                <option value="{{ $curso->id }}" @selected((string) request('curso_id') === (string) $curso->id)>{{ $curso->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Ver reporte</x-primary-button>
                    <a href="{{ route('reportes.aporte-alumno.pdf', ['gestion' => $gestion?->id, 'curso_id' => request('curso_id')]) }}" target="_blank">
                        <x-secondary-button type="button">Descargar PDF</x-secondary-button>
                    </a>
                    <a href="{{ route('reportes.aporte-alumno.excel', ['gestion' => $gestion?->id, 'curso_id' => request('curso_id')]) }}">
                        <x-secondary-button type="button">Descargar Excel</x-secondary-button>
                    </a>
                    {{-- Fecha de corte: las cuotas con vencimiento anterior a esta fecha se consideran vencidas. --}}
                    <span class="text-xs text-slate-500 self-center">
                        Corte: {{ \Illuminate\Support\Carbon::parse($hoy)->format('d/m/Y') }}
                    </span>
                </form>
            </div>

            {{--
                Tarjetas de totales. Muestran exactamente los mismos valores que el PDF y el Excel,
                porque los tres salen del mismo cálculo. Los montos se formatean con la clase Dinero.
            --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Emitido</div>
                    <div class="text-lg font-bold text-slate-800">{{ \App\Support\Dinero::formato($totales['emitido']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Recaudado</div>
                    <div class="text-lg font-bold text-emerald-700">{{ \App\Support\Dinero::formato($totales['pagado']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Vencido</div>
                    <div class="text-lg font-bold text-rose-700">{{ \App\Support\Dinero::formato($totales['vencido']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Saldo</div>
                    <div class="text-lg font-bold text-slate-800">{{ \App\Support\Dinero::formato($totales['saldo']) }}</div>
                </div>
            </div>

            {{-- Tabla de detalle, una fila por alumno. --}}
            <div class="bg-white shadow-sm rounded-lg p-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                            <th class="py-2 pr-3">Código</th>
                            <th class="py-2 pr-3">Alumno</th>
                            <th class="py-2 pr-3">Curso</th>
                            <th class="py-2 pr-3 text-right">Vencidas</th>
                            <th class="py-2 pr-3 text-right">Emitido</th>
                            <th class="py-2 pr-3 text-right">Recaudado</th>
                            <th class="py-2 pr-3 text-right">Vencido</th>
                            <th class="py-2 text-right">Saldo</th>
                            <th class="py-2 pl-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Cada fila incluye un enlace al estado de cuenta del alumno para ver el detalle de sus cuotas. --}}
                        @forelse ($filas as $fila)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3">{{ $fila['codigo'] }}</td>
                                <td class="py-2 pr-3 font-medium">{{ $fila['nombre'] }}</td>
                                <td class="py-2 pr-3">{{ $fila['curso'] ?? '—' }}</td>
                                <td class="py-2 pr-3 text-right">{{ $fila['cuotas_vencidas'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($fila['emitido']) }}</td>
                                <td class="py-2 pr-3 text-right text-emerald-700">{{ \App\Support\Dinero::formato($fila['pagado']) }}</td>
                                <td class="py-2 pr-3 text-right text-rose-700">{{ \App\Support\Dinero::formato($fila['vencido']) }}</td>
                                <td class="py-2 text-right font-semibold">{{ \App\Support\Dinero::formato($fila['saldo']) }}</td>
                                <td class="py-2 pl-3 text-right">
                                    <a class="text-sky-700 text-xs hover:underline" href="{{ route('aporte.estado_cuenta', $fila['estudiante_id']) }}">Estado de cuenta</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-6 text-center text-slate-500">Sin cuotas emitidas para el filtro seleccionado.</td></tr>
                        @endforelse
                    </tbody>
                    {{-- Fila de totales al pie de la tabla. --}}
                    <tfoot>
                        <tr class="font-bold bg-slate-50">
                            <td class="py-2 pr-3" colspan="3">TOTALES</td>
                            <td class="py-2 pr-3 text-right">{{ $totales['cuotas_vencidas'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($totales['emitido']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($totales['pagado']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($totales['vencido']) }}</td>
                            <td class="py-2 text-right">{{ \App\Support\Dinero::formato($totales['saldo']) }}</td>
                            <td class="py-2 pl-3"></td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Nota aclaratoria sobre el origen de los datos. --}}
                <p class="text-xs text-slate-500 mt-4">
                    La obligación del aporte es del alumno. Filas por `AporteService::estadoDeCuenta()`:
                    pantalla, PDF y Excel coinciden al centavo.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
