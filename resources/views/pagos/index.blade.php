{{--
    Vista: Listado de pagos (QR + WhatsApp). Flujo económico anterior.
    Muestra los pagos registrados con su referencia, concepto, monto y estado, con un filtro
    por estado (pendiente, en revisión, confirmado o rechazado).
    Recibe del controlador $pagos (colección paginada) y $estado (filtro seleccionado).
    Los padres ven sus propios pagos; tesorería, con el permiso pagos.confirmar, además
    accede a los pagos pendientes de confirmación.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pagos</h2>
            {{-- Accesos según permisos: revisar pendientes (tesorería) y registrar un nuevo pago. --}}
            <div class="flex flex-wrap gap-2 sm:gap-3 sm:items-center">
                @can('pagos.confirmar')
                    <a href="{{ route('pagos.pendientes') }}" class="text-sm text-indigo-600">Pendientes de confirmación</a>
                @endcan
                @can('pagos.ver')
                    <a href="{{ route('pagos.create') }}"><x-primary-button type="button">Nuevo pago</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>
    <div class="py-8"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @include('partials.flash')
        <div class="bg-white shadow-sm rounded-lg p-6">
            {{-- Filtro por estado. Las opciones se generan desde un arreglo y se reemplaza el guion bajo por espacio para mostrarlas. --}}
            <form method="GET" class="mb-4 flex flex-col sm:flex-row gap-2">
                <select name="estado" class="border-gray-300 rounded-md shadow-sm sm:w-56">
                    <option value="">Todos</option>
                    @foreach (['pendiente','en_revision','confirmado','rechazado'] as $est)
                        <option value="{{ $est }}" @selected($estado === $est)>{{ str_replace('_',' ', $est) }}</option>
                    @endforeach
                </select>
                <x-primary-button>Filtrar</x-primary-button>
            </form>
            {{-- Tabla de pagos con enlace al detalle de cada uno. --}}
            <div class="overflow-x-auto -mx-6 px-6 sm:mx-0 sm:px-0">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left border-b"><th class="py-2">Referencia</th><th>Concepto</th><th>Monto</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pagos as $pago)
                        <tr class="border-b">
                            <td class="py-2">{{ $pago->referencia }}</td>
                            <td>{{ $pago->cargo?->concepto }}</td>
                            <td>Bs {{ number_format($pago->monto, 2) }}</td>
                            <td>{{ $pago->estado }}</td>
                            <td class="text-right"><a href="{{ route('pagos.show', $pago) }}" class="text-indigo-600">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">No hay pagos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            {{-- Paginación. --}}
            <div class="mt-4">{{ $pagos->links() }}</div>
        </div>
    </div></div>
</x-app-layout>
