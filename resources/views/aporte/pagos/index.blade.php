{{--
    Vista: Listado de pagos validados
    Muestra los pagos del aporte que ya fueron confirmados por Administración,
    cada uno con su número de comprobante interno. El responsable familiar ve
    solo sus pagos; el personal administrativo ve todos y puede filtrarlos.

    Variables que recibe del controlador:
    - $pagos: pagos paginados.
    - $estadoFiltro: estado seleccionado en el filtro (validado o anulado).
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Pagos validados</h2>
            {{-- Solo el personal autorizado registra pagos recibidos en efectivo en secretaría --}}
            @can('aporte.avisos.gestionar')
                <a href="{{ route('aporte.pagos.create') }}"><x-primary-button type="button">Registrar pago en efectivo</x-primary-button></a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- El filtro por estado no se muestra a la familia, ya que solo ve sus propios pagos --}}
                @if (! auth()->user()->esResponsableFamiliar())
                    <form method="GET" class="mb-4 flex items-end gap-3">
                        <div>
                            <x-input-label for="estado" value="Estado" />
                            <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1">
                                <option value="">Todos</option>
                                @foreach (\App\Models\Pago::ESTADOS as $value => $label)
                                    <option value="{{ $value }}" @selected((string) $estadoFiltro === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Filtrar</x-primary-button>
                    </form>
                @endif

                {{--
                    Tabla de pagos. Los anulados se muestran atenuados para distinguirlos, y la
                    columna del responsable solo aparece para el personal de la unidad educativa.
                --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Comprobante</th>
                                @if (! auth()->user()->esResponsableFamiliar())
                                    <th class="pr-3">Responsable</th>
                                @endif
                                <th class="pr-3 text-right">Monto</th>
                                <th class="pr-3">Validado</th>
                                <th class="pr-3">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pagos as $pago)
                                <tr class="border-b border-slate-100 {{ $pago->estado === 'anulado' ? 'opacity-60' : '' }}">
                                    <td class="py-2.5 pr-3 font-mono text-xs">{{ $pago->comprobante_numero }}</td>
                                    @if (! auth()->user()->esResponsableFamiliar())
                                        <td class="pr-3">{{ $pago->padre?->name }}</td>
                                    @endif
                                    <td class="pr-3 text-right font-medium">{{ \App\Support\Dinero::formato($pago->montoCentavos()) }}</td>
                                    <td class="pr-3 whitespace-nowrap">{{ optional($pago->validado_en)->format('d/m/Y H:i') }}</td>
                                    <td class="pr-3">
                                        @php($colores = ['validado' => 'bg-emerald-100 text-emerald-800', 'anulado' => 'bg-rose-100 text-rose-800'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$pago->estado] ?? '' }}">{{ $pago->nombreEstado() }}</span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <a href="{{ route('aporte.pagos.show', $pago) }}" class="text-sky-700 hover:underline">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">Aún no hay pagos validados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Enlaces de paginación --}}
                <div class="mt-4">{{ $pagos->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
