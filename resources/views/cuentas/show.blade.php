{{--
    Vista: Detalle de un cargo
    Muestra los datos de un cargo económico (concepto, padre, estudiante, monto,
    lo que falta pagar y fechas) junto con la lista de pagos relacionados.

    Variables que recibe del controlador:
    - $cargo: el cargo con su padre, estudiante y pagos.
--}}
{{-- Calculamos una sola vez el monto pendiente porque se usa en varias partes de la vista --}}
@php
    $pendiente = $cargo->montoPendiente();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Detalle de cuenta</h2>
            {{--
                El botón para registrar un pago solo aparece si el usuario tiene permiso y el cargo
                todavía tiene saldo (estado pendiente o parcial). Si ya está pagado no tiene sentido.
            --}}
            <div class="flex flex-wrap gap-2">
                @can('pagos.ver')
                    @if (in_array($cargo->estado, ['pendiente', 'parcial'], true) && $pendiente > 0)
                        <a href="{{ route('pagos.create', $cargo) }}"><x-primary-button type="button">Registrar pago</x-primary-button></a>
                    @endif
                @endcan
                <a href="{{ route('cuentas.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{-- Datos generales del cargo --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Concepto</dt>
                        <dd class="font-medium">{{ $cargo->concepto }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Padre</dt>
                        <dd class="font-medium">{{ $cargo->padre?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Estudiante</dt>
                        <dd class="font-medium">{{ $cargo->estudiante?->nombreCompleto() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Monto</dt>
                        <dd class="font-medium">Bs. {{ number_format((float) $cargo->monto, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Pendiente</dt>
                        <dd class="font-medium">Bs. {{ number_format($pendiente, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Estado</dt>
                        <dd class="font-medium">{{ ucfirst($cargo->estado) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Emisión</dt>
                        <dd class="font-medium">{{ optional($cargo->fecha_emision)->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Vencimiento</dt>
                        <dd class="font-medium">{{ optional($cargo->fecha_vencimiento)->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-slate-500">Observación</dt>
                        <dd class="font-medium">{{ $cargo->observacion ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Tabla de pagos que se registraron para este cargo, con enlace al detalle de cada uno --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-4">Pagos asociados</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Referencia</th>
                                <th class="pr-3">Monto</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Solicitado</th>
                                <th class="pr-3">Confirmado por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cargo->pagos as $pago)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3 font-medium">{{ $pago->referencia }}</td>
                                    <td class="pr-3">Bs. {{ number_format((float) $pago->monto, 2) }}</td>
                                    <td class="pr-3">{{ str_replace('_', ' ', ucfirst($pago->estado)) }}</td>
                                    <td class="pr-3">{{ optional($pago->solicitado_en)->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="pr-3">{{ $pago->confirmador?->name ?? '—' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('pagos.show', $pago) }}" class="text-sky-700 hover:underline">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">Aún no hay pagos para este cargo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
