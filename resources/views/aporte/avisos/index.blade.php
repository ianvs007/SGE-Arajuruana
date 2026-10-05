{{--
    Vista: Listado de avisos de pago
    La comparten dos tipos de usuario: el responsable familiar ve solo sus propios
    avisos y la deuda de sus hijos; Administración ve todos los avisos para
    validarlos o rechazarlos.

    Variables que recibe del controlador:
    - $avisos: avisos de pago paginados (ya filtrados según el usuario).
    - $pendientes: cantidad de avisos que esperan validación.
    - $deudaHijos: cuotas pendientes de los hijos, o null si el usuario no es familia.
    - $estadoFiltro: estado elegido en el filtro.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Avisos de pago (§14)</h2>
            {{--
                Botones de acción según permisos: el responsable puede informar un pago hecho
                con QR y el personal puede registrar un pago recibido en efectivo en secretaría.
            --}}
            <div class="flex gap-2">
                @can('aporte.avisos.informar')
                    <a href="{{ route('aporte.avisos.create') }}"><x-primary-button type="button">Informar un pago</x-primary-button></a>
                @endcan
                @can('aporte.avisos.gestionar')
                    <a href="{{ route('aporte.pagos.create') }}"><x-secondary-button type="button">Registrar pago en efectivo</x-secondary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            {{-- Alerta cuando hay avisos esperando validación, recordando que todavía no reducen la deuda --}}
            @if ($pendientes > 0)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm">
                    Hay <strong>{{ $pendientes }}</strong> aviso(s) pendiente(s) de validación. Un aviso pendiente
                    NO reduce la deuda del alumno hasta que Administración valide el pago.
                </div>
            @endif

            {{--
                Resumen de la deuda actual de los hijos. Solo aparece para el responsable
                familiar (para otros roles $deudaHijos llega como null). Agrupamos las cuotas
                por estudiante y mostramos el total, con enlace a su estado de cuenta.
            --}}
            @if ($deudaHijos !== null)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-2 text-sm">Deuda actual de sus representados (gestión actual)</h3>
                    @if ($deudaHijos->isEmpty())
                        <p class="text-sm text-slate-500">No hay cuotas pendientes. 🎉</p>
                    @else
                        <ul class="text-sm divide-y divide-slate-100">
                            @foreach ($deudaHijos->groupBy('estudiante_id') as $alumnoId => $cuotasAlumno)
                                @php($totalAlumno = $cuotasAlumno->sum(fn ($c) => $c->saldoCentavos()))
                                <li class="py-2 flex flex-wrap justify-between gap-2">
                                    <span>{{ $cuotasAlumno->first()->estudiante?->nombreCompleto() }}
                                        <span class="text-xs text-slate-400">({{ $cuotasAlumno->count() }} cuota{{ $cuotasAlumno->count() > 1 ? 's' : '' }})</span>
                                    </span>
                                    <a href="{{ route('aporte.estado_cuenta', $alumnoId) }}" class="font-medium text-sky-700 hover:underline">
                                        {{ \App\Support\Dinero::formato($totalAlumno) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Filtro por estado del aviso (pendiente, validado, rechazado o anulado) --}}
                <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1">
                            <option value="">Todos</option>
                            @foreach (\App\Models\AvisoPago::ESTADOS as $value => $label)
                                <option value="{{ $value }}" @selected((string) $estadoFiltro === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Filtrar</x-primary-button>
                </form>

                {{--
                    Tabla de avisos. La columna "Informado por" se oculta al responsable familiar,
                    porque todos los avisos que ve son suyos. El estado se muestra con un color.
                --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Referencia</th>
                                @if (! auth()->user()->esResponsableFamiliar())
                                    <th class="pr-3">Informado por</th>
                                @endif
                                <th class="pr-3 text-right">Monto declarado</th>
                                <th class="pr-3">Informado</th>
                                <th class="pr-3">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($avisos as $aviso)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3 font-mono text-xs">{{ $aviso->referencia }}</td>
                                    @if (! auth()->user()->esResponsableFamiliar())
                                        <td class="pr-3">{{ $aviso->padre?->name }}</td>
                                    @endif
                                    <td class="pr-3 text-right">{{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</td>
                                    <td class="pr-3 whitespace-nowrap">{{ optional($aviso->informado_en)->format('d/m/Y H:i') }}</td>
                                    <td class="pr-3">
                                        @php($colores = ['pendiente' => 'bg-amber-100 text-amber-800', 'validado' => 'bg-emerald-100 text-emerald-800', 'rechazado' => 'bg-rose-100 text-rose-800', 'anulado' => 'bg-slate-200 text-slate-600'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$aviso->estado] ?? '' }}">{{ $aviso->nombreEstado() }}</span>
                                    </td>
                                    {{-- Si el aviso está pendiente y el usuario puede gestionarlo, el enlace invita a validarlo --}}
                                    <td class="text-right whitespace-nowrap">
                                        <a href="{{ route('aporte.avisos.show', $aviso) }}" class="text-sky-700 hover:underline">
                                            {{ $aviso->estaPendiente() && auth()->user()->can('aporte.avisos.gestionar') ? 'Validar / Rechazar' : 'Ver' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">No hay avisos de pago registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Enlaces de paginación --}}
                <div class="mt-4">{{ $avisos->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
