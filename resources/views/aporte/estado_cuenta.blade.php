<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Estado de cuenta — {{ $estudiante->nombreCompleto() }}</h2>
            <a href="{{ auth()->user()->esResponsableFamiliar() ? route('dashboard') : route('aporte.cuotas.index') }}">
                <x-secondary-button type="button">Volver</x-secondary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div>
                        <x-input-label for="gestion" value="Gestión" />
                        <select id="gestion" name="gestion" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1"
                            onchange="this.form.submit()">
                            @foreach ($gestiones as $g)
                                <option value="{{ $g->id }}" @selected($gestion && (string) request('gestion') === (string) $g->id)>{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="text-xs text-slate-500 pb-2">
                        La obligación es del alumno: cada hijo tiene su propia cuenta (§14).
                    </div>
                </form>
            </div>

            {{-- Totales: mismos números que usan reportes PDF/Excel (centavos, §14) --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Emitido</div>
                    <div class="text-lg font-semibold text-slate-800">{{ \App\Support\Dinero::formato($totales['emitido']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Pagado</div>
                    <div class="text-lg font-semibold text-emerald-700">{{ \App\Support\Dinero::formato($totales['pagado']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Saldo</div>
                    <div class="text-lg font-semibold text-slate-800">{{ \App\Support\Dinero::formato($totales['saldo']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-slate-500">Saldo vencido</div>
                    <div class="text-lg font-semibold text-rose-700">{{ \App\Support\Dinero::formato($totales['vencido']) }}</div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-3">Cuotas del periodo</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Periodo</th>
                                <th class="pr-3 text-right">Monto</th>
                                <th class="pr-3 text-right">Pagado</th>
                                <th class="pr-3 text-right">Saldo</th>
                                <th class="pr-3">Vence</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Pagos aplicados</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cuotas as $cuota)
                                @php($vencida = $cuota->estaVencida())
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">{{ $cuota->etiquetaPeriodo() }}</td>
                                    <td class="pr-3 text-right">{{ \App\Support\Dinero::formato($cuota->montoCentavos()) }}</td>
                                    <td class="pr-3 text-right text-emerald-700">{{ \App\Support\Dinero::formato($cuota->pagadoCentavos()) }}</td>
                                    <td class="pr-3 text-right font-medium {{ $vencida ? 'text-rose-700' : '' }}">
                                        {{ $cuota->estado === 'exenta' ? '—' : \App\Support\Dinero::formato($cuota->saldoCentavos()) }}
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
                                    <td class="pr-3 text-xs">
                                        @foreach ($cuota->aplicaciones as $aplicacion)
                                            @if ($aplicacion->pago && $aplicacion->pago->estaValidado())
                                                <a href="{{ route('aporte.pagos.show', $aplicacion->pago) }}" class="text-sky-700 hover:underline block">
                                                    {{ $aplicacion->pago->comprobante_numero }} · {{ \App\Support\Dinero::formato($aplicacion->montoCentavos()) }}
                                                </a>
                                            @endif
                                        @endforeach
                                        @if ($cuota->estado === 'exenta' && $cuota->observacion)
                                            <span class="text-slate-500">{{ $cuota->observacion }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-500">
                                        No hay cuotas emitidas para este alumno en la gestión seleccionada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('aporte.avisos.informar')
                    <div class="mt-4 flex flex-wrap gap-2 items-center">
                        <a href="{{ route('aporte.avisos.create') }}"><x-primary-button type="button">Informar un pago</x-primary-button></a>
                        <span class="text-xs text-slate-500">
                            El aviso con nota escrita NO reduce la deuda hasta que Administración lo valide (§14).
                        </span>
                    </div>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
