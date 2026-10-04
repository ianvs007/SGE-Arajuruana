{{--
    Vista: Listado de cuentas (cargos)
    Muestra los cargos económicos registrados a los padres o responsables, por
    ejemplo cobros por algún concepto puntual, con su monto y estado. La usa el
    personal administrativo para llevar el control de lo que se debe cobrar.

    Variables que recibe del controlador:
    - $cargos: cargos paginados con su padre y estudiante.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Cuentas / cargos</h2>
            {{-- Solo quien tiene permiso de gestionar cuentas puede crear un cargo nuevo --}}
            @can('cuentas.gestionar')
                <a href="{{ route('cuentas.create') }}"><x-primary-button type="button">Nuevo cargo</x-primary-button></a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Tabla de cargos con fecha, concepto, padre, estudiante, monto en bolivianos y estado --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Emisión</th>
                                <th class="pr-3">Concepto</th>
                                <th class="pr-3">Padre</th>
                                <th class="pr-3">Estudiante</th>
                                <th class="pr-3">Monto</th>
                                <th class="pr-3">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cargos as $cargo)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">{{ optional($cargo->fecha_emision)->format('d/m/Y') }}</td>
                                    <td class="pr-3">{{ $cargo->concepto }}</td>
                                    <td class="pr-3">{{ $cargo->padre?->name }}</td>
                                    <td class="pr-3">{{ $cargo->estudiante?->nombreCompleto() ?? '—' }}</td>
                                    <td class="pr-3">Bs. {{ number_format((float) $cargo->monto, 2) }}</td>
                                    <td class="pr-3">{{ ucfirst($cargo->estado) }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('cuentas.show', $cargo) }}" class="text-sky-700 hover:underline">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-500">No hay cargos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Enlaces de paginación --}}
                <div class="mt-4">{{ $cargos->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
