<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Cuotas de aporte (§14)</h2>
            <div class="flex gap-2">
                <a href="{{ route('aporte.parametros.edit') }}"><x-secondary-button type="button">Parámetros</x-secondary-button></a>
                @can('aporte.cuotas.gestionar')
                    <form method="POST" action="{{ route('aporte.cuotas.generar') }}" class="flex items-center gap-2"
                        onsubmit="return confirm('¿Generar las cuotas de las inscripciones activas de esta gestión? Las ya emitidas no se duplican ni se recalculan.')">
                        @csrf
                        <input type="hidden" name="gestion_id" value="{{ $gestion?->id }}">
                        {{-- `:disabled` (no `@disabled`): la directiva dentro de la
                             etiqueta del componente rompe la compilación Blade. --}}
                        <x-primary-button :disabled="! $gestion">Generar cuotas{{ $gestion ? ' — '.$gestion->nombre : '' }}</x-primary-button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            {{-- Totales en Bs (centavos → formato, §14: mismos totales que PDF/Excel) --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Emitido</div>
                    <div class="text-lg font-semibold text-slate-800">{{ \App\Support\Dinero::formato($totales['emitido']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Recaudado</div>
                    <div class="text-lg font-semibold text-emerald-700">{{ \App\Support\Dinero::formato($totales['pagado']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Saldo por cobrar</div>
                    <div class="text-lg font-semibold text-slate-800">{{ \App\Support\Dinero::formato($totales['saldo']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Saldo vencido</div>
                    <div class="text-lg font-semibold text-rose-700">{{ \App\Support\Dinero::formato($totales['vencido']) }}</div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="mb-4 grid sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <x-input-label for="q" value="Buscar alumno" />
                        <x-text-input id="q" name="q" class="block mt-1 w-full" :value="$q" placeholder="Nombre o código" />
                    </div>
                    <div>
                        <x-input-label for="gestion" value="Gestión" />
                        <select id="gestion" name="gestion" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Actual</option>
                            @foreach ($gestiones as $g)
                                <option value="{{ $g->id }}" @selected($gestion && (string) request('gestion') === (string) $g->id)>{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todos</option>
                            @foreach (\App\Models\CuotaAporte::ESTADOS as $value => $label)
                                <option value="{{ $value }}" @selected((string) $estadoFiltro === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-primary-button>Filtrar</x-primary-button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Alumno</th>
                                <th class="pr-3">Periodo</th>
                                <th class="pr-3 text-right">Monto</th>
                                <th class="pr-3 text-right">Saldo</th>
                                <th class="pr-3">Vence</th>
                                <th class="pr-3">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cuotas as $cuota)
                                @php($vencida = $cuota->estaVencida())
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">
                                        {{ $cuota->estudiante?->nombreCompleto() }}
                                        <div class="text-xs text-slate-400">{{ $cuota->estudiante?->codigo }}</div>
                                    </td>
                                    <td class="pr-3">{{ $cuota->etiquetaPeriodo() }}</td>
                                    <td class="pr-3 text-right">{{ \App\Support\Dinero::formato($cuota->montoCentavos()) }}</td>
                                    <td class="pr-3 text-right font-medium {{ $vencida ? 'text-rose-700' : '' }}">
                                        {{ \App\Support\Dinero::formato($cuota->saldoCentavos()) }}
                                    </td>
                                    <td class="pr-3 whitespace-nowrap">
                                        {{ optional($cuota->fecha_vencimiento)->format('d/m/Y') }}
                                        @if ($vencida)
                                            <span class="ml-1 text-[11px] bg-rose-100 text-rose-800 rounded px-1.5 py-0.5">Vencida</span>
                                        @endif
                                    </td>
                                    <td class="pr-3">
                                        @php($colores = ['pendiente' => 'bg-amber-100 text-amber-800', 'parcial' => 'bg-sky-100 text-sky-800', 'pagada' => 'bg-emerald-100 text-emerald-800', 'exenta' => 'bg-slate-200 text-slate-600'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$cuota->estado] ?? '' }}">{{ $cuota->nombreEstado() }}</span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <a href="{{ route('aporte.estado_cuenta', $cuota->estudiante_id) }}?gestion={{ $cuota->gestion_id }}" class="text-sky-700 hover:underline">Estado de cuenta</a>
                                        @can('aporte.cuotas.gestionar')
                                            @if (! in_array($cuota->estado, ['pagada', 'exenta'], true) && $cuota->pagadoCentavos() === 0)
                                                <form method="POST" action="{{ route('aporte.cuotas.eximir', $cuota) }}" class="inline ml-2"
                                                    onsubmit="const m = prompt('Motivo de la exención (obligatorio, queda auditado):'); if (!m) return false; this.querySelector('[name=observacion]').value = m; return true;">
                                                    @csrf
                                                    <input type="hidden" name="observacion" value="">
                                                    <button type="submit" class="text-slate-500 hover:text-rose-700 hover:underline">Eximir</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-500">
                                        No hay cuotas para los filtros seleccionados.
                                        @can('aporte.cuotas.gestionar')
                                            Genere las cuotas de las inscripciones activas con el botón superior.
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $cuotas->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
