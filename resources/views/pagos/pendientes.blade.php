{{--
    Vista: Pagos pendientes de confirmación. Flujo económico anterior.
    Bandeja de trabajo de tesorería: lista los pagos que los padres generaron y que todavía no
    fueron confirmados ni rechazados. Desde aquí se abre cada pago para revisar el comprobante.
    Recibe del controlador $pagos, una colección paginada de pagos pendientes con su padre y su cargo.
    La usa el personal con el permiso pagos.confirmar.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pagos pendientes de confirmación</h2>
    </x-slot>
    <div class="py-8"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @include('partials.flash')
        <div class="bg-white shadow-sm rounded-lg p-6">
            {{-- Tabla con los pagos por revisar: referencia, padre que pagó, concepto y monto. --}}
            <div class="overflow-x-auto -mx-6 px-6 sm:mx-0 sm:px-0">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left border-b"><th class="py-2">Referencia</th><th>Padre</th><th>Concepto</th><th>Monto</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pagos as $pago)
                        <tr class="border-b">
                            <td class="py-2">{{ $pago->referencia }}</td>
                            <td>{{ $pago->padre?->name }}</td>
                            <td>{{ $pago->cargo?->concepto }}</td>
                            <td>Bs {{ number_format($pago->monto, 2) }}</td>
                            <td class="text-right"><a href="{{ route('pagos.show', $pago) }}" class="text-indigo-600">Revisar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">No hay pagos pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            {{-- Paginación. --}}
            <div class="mt-4">{{ $pagos->links() }}</div>
        </div>
    </div></div>
</x-app-layout>
