<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Aporte por curso</h2>
            <a href="{{ route('reportes.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" action="{{ route('reportes.aporte-curso') }}" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <x-input-label for="gestion" value="Gestión" />
                        <select id="gestion" name="gestion" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1">
                            @foreach ($gestiones as $g)
                                <option value="{{ $g->id }}" @selected($gestion && $gestion->id === $g->id)>{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Ver reporte</x-primary-button>
                    <a href="{{ route('reportes.aporte-curso.pdf', ['gestion' => $gestion?->id]) }}" target="_blank">
                        <x-secondary-button type="button">Descargar PDF</x-secondary-button>
                    </a>
                    <a href="{{ route('reportes.aporte-curso.excel', ['gestion' => $gestion?->id]) }}">
                        <x-secondary-button type="button">Descargar Excel</x-secondary-button>
                    </a>
                    <span class="text-xs text-slate-500 self-center">
                        Corte: {{ \Illuminate\Support\Carbon::parse($data['hoy'])->format('d/m/Y') }}
                    </span>
                </form>
            </div>

            {{-- Tarjetas de totales — los MISMOS valores del PDF/Excel (§16) --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Emitido</div>
                    <div class="text-lg font-bold text-slate-800">{{ \App\Support\Dinero::formato($data['totales']['emitido']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Recaudado</div>
                    <div class="text-lg font-bold text-emerald-700">{{ \App\Support\Dinero::formato($data['totales']['pagado']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Vencido</div>
                    <div class="text-lg font-bold text-rose-700">{{ \App\Support\Dinero::formato($data['totales']['vencido']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Saldo</div>
                    <div class="text-lg font-bold text-slate-800">{{ \App\Support\Dinero::formato($data['totales']['saldo']) }}</div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                            <th class="py-2 pr-3">Curso</th>
                            <th class="py-2 pr-3 text-right">Alumnos</th>
                            <th class="py-2 pr-3 text-right">Cuotas vencidas</th>
                            <th class="py-2 pr-3 text-right">Emitido</th>
                            <th class="py-2 pr-3 text-right">Recaudado</th>
                            <th class="py-2 pr-3 text-right">Vencido</th>
                            <th class="py-2 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['filas'] as $fila)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3 font-medium">{{ $fila['nombre'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $fila['alumnos'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ $fila['cuotas_vencidas'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($fila['emitido']) }}</td>
                                <td class="py-2 pr-3 text-right text-emerald-700">{{ \App\Support\Dinero::formato($fila['pagado']) }}</td>
                                <td class="py-2 pr-3 text-right text-rose-700">{{ \App\Support\Dinero::formato($fila['vencido']) }}</td>
                                <td class="py-2 text-right font-semibold">{{ \App\Support\Dinero::formato($fila['saldo']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-500">Sin cuotas emitidas en esta gestión.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-bold bg-slate-50">
                            <td class="py-2 pr-3">TOTALES</td>
                            <td class="py-2 pr-3 text-right">{{ $data['totales']['alumnos'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ $data['totales']['cuotas_vencidas'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($data['totales']['emitido']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($data['totales']['pagado']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Support\Dinero::formato($data['totales']['vencido']) }}</td>
                            <td class="py-2 text-right">{{ \App\Support\Dinero::formato($data['totales']['saldo']) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <p class="text-xs text-slate-500 mt-4">
                    Las cuotas exentas no cuentan. Vencido = saldo con fecha de vencimiento anterior al corte.
                    Montos en centavos enteros (§14); PDF y Excel usan esta misma fuente (§16).
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
