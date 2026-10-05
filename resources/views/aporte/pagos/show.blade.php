{{--
    Vista: Detalle de un pago
    Muestra un pago validado con sus datos, la forma de pago (QR verificado en
    el banco o efectivo en secretaría) y cómo se distribuyó entre las cuotas de
    los hijos. Desde aquí se puede descargar el comprobante en PDF y, si se
    tiene permiso, anular el pago.

    Variables que recibe del controlador:
    - $pago: el pago con sus relaciones (padre, confirmador, aviso, aplicaciones, anulación).
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Pago {{ $pago->comprobante_numero }}</h2>
            {{-- Botones para abrir el comprobante PDF en otra pestaña y para volver al listado --}}
            <div class="flex gap-2">
                <a href="{{ route('aporte.pagos.comprobante', $pago) }}" target="_blank"><x-secondary-button type="button">Comprobante PDF</x-secondary-button></a>
                <a href="{{ route('aporte.pagos.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{-- Aviso destacado cuando el pago fue anulado, con quién lo anuló, cuándo y por qué --}}
            @if ($pago->estado === 'anulado')
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded text-sm">
                    <strong>Pago anulado.</strong>
                    {{ $pago->anulacion ? 'Anulado por '.$pago->anulacion->anulador?->name.' el '.optional($pago->anulacion->anulado_en)->format('d/m/Y H:i').' — motivo: '.$pago->anulacion->motivo : '' }}
                    Las cuotas recuperaron su saldo; este comprobante ya no es válido.
                </div>
            @endif

            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    {{--
                        Datos generales del pago: comprobante, responsable, monto, quién lo validó y
                        su origen (un aviso de pago por QR o un registro de efectivo en secretaría).
                    --}}
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <h3 class="font-semibold text-slate-800">Datos del pago</h3>
                            @php($colores = ['validado' => 'bg-emerald-100 text-emerald-800', 'anulado' => 'bg-rose-100 text-rose-800'])
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $colores[$pago->estado] ?? '' }}">{{ $pago->nombreEstado() }}</span>
                        </div>
                        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-slate-500">Comprobante interno</dt>
                                <dd class="font-mono font-medium">{{ $pago->comprobante_numero }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Referencia</dt>
                                <dd class="font-mono font-medium">{{ $pago->referencia }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Responsable familiar</dt>
                                <dd class="font-medium">{{ $pago->padre?->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Monto validado</dt>
                                <dd class="font-medium text-emerald-700">{{ \App\Support\Dinero::formato($pago->montoCentavos()) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Validado por</dt>
                                <dd class="font-medium">{{ $pago->confirmador?->name }} · {{ optional($pago->validado_en)->format('d/m/Y H:i') }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Forma de pago</dt>
                                <dd class="font-medium">{{ $pago->nombreMetodo() }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Origen</dt>
                                <dd class="font-medium">
                                    @if ($pago->aviso)
                                        Aviso {{ $pago->aviso->referencia }}
                                        (<a href="{{ route('aporte.avisos.show', $pago->aviso) }}" class="text-sky-700 hover:underline">ver</a>)
                                    @else
                                        Registro directo en secretaría
                                    @endif
                                </dd>
                            </div>
                            @if ($pago->operacion_bancaria)
                                <div>
                                    <dt class="text-slate-500">Nº de operación bancaria verificada</dt>
                                    <dd class="font-mono font-medium">{{ $pago->operacion_bancaria }}</dd>
                                </div>
                            @endif
                            {{-- La nota del responsable y la observación del operador solo se muestran si existen --}}
                            @if ($pago->nota_responsable)
                                <div class="sm:col-span-2">
                                    <dt class="text-slate-500">Nota del responsable</dt>
                                    <dd class="font-medium whitespace-pre-wrap">{{ $pago->nota_responsable }}</dd>
                                </div>
                            @endif
                            @if ($pago->observacion_operador)
                                <div class="sm:col-span-2">
                                    <dt class="text-slate-500">Observación del operador</dt>
                                    <dd class="font-medium">{{ $pago->observacion_operador }}</dd>
                                </div>
                            @endif
                        </dl>
                        <p class="mt-4 text-xs text-slate-400">
                            Comprobante interno de la Unidad Educativa — <strong>no válido como factura fiscal</strong> (§15).
                        </p>
                    </div>

                    {{--
                        Distribución del pago entre los hijos y los meses. Mientras recorremos las
                        aplicaciones vamos sumando lo aplicado en $sumaAplicada para mostrar el total.
                    --}}
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-semibold text-slate-800 mb-3">Distribución del pago</h3>
                        <div class="overflow-x-auto -mx-6 px-6 sm:mx-0 sm:px-0">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left border-b border-slate-200 text-slate-500">
                                    <th class="py-2 pr-3">Alumno</th>
                                    <th class="pr-3">Periodo</th>
                                    <th class="pr-3 text-right">Aplicado</th>
                                    <th class="pr-3 text-right">Saldo cuota tras pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($sumaAplicada = 0)
                                @foreach ($pago->aplicaciones as $aplicacion)
                                    @php($sumaAplicada += $aplicacion->montoCentavos())
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-3">{{ $aplicacion->estudiante?->nombreCompleto() }}</td>
                                        <td class="pr-3">{{ $aplicacion->cuota?->etiquetaPeriodo() }}</td>
                                        <td class="pr-3 text-right font-medium">{{ \App\Support\Dinero::formato($aplicacion->montoCentavos()) }}</td>
                                        <td class="pr-3 text-right">{{ \App\Support\Dinero::formato($aplicacion->cuota?->saldoCentavos() ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="pt-2 font-semibold">Total aplicado</td>
                                    <td class="pt-2 text-right font-semibold">{{ \App\Support\Dinero::formato($sumaAplicada) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>
                        {{-- Control de consistencia: si la suma no coincide con el monto validado se advierte --}}
                        @if ($sumaAplicada !== $pago->montoCentavos())
                            <p class="mt-2 text-xs text-rose-600">Inconsistencia: la distribución no suma el monto validado. Contacte a Administración.</p>
                        @endif
                    </div>
                </div>

                <div class="space-y-6">
                    {{--
                        Formulario para anular el pago. Solo lo ve quien tiene el permiso de anular pagos
                        (Administración) y solo si el pago sigue validado. Se pide un motivo obligatorio
                        porque la anulación queda registrada y no se borra nada del historial.
                    --}}
                    @can('aporte.pagos.anular')
                        @if ($pago->estaValidado())
                            <div class="bg-white shadow-sm rounded-lg p-6">
                                <h3 class="font-semibold text-slate-800 mb-1">Anular pago</h3>
                                <p class="text-xs text-slate-500 mb-4">
                                    La anulación es trazable: revierte la distribución (las cuotas recuperan su saldo),
                                    conserva el registro histórico y deja constancia en auditoría. No existe borrado silencioso.
                                </p>
                                <form method="POST" action="{{ route('aporte.pagos.anular', $pago) }}" class="space-y-3"
                                    onsubmit="return confirm('¿Anular este pago validado? Las cuotas recuperarán su saldo.')">
                                    @csrf
                                    <div>
                                        <x-input-label for="motivo" value="Motivo de la anulación (obligatorio)" />
                                        <textarea id="motivo" name="motivo" rows="3" maxlength="500" required
                                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full"></textarea>
                                        <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
                                    </div>
                                    <x-danger-button>Anular pago</x-danger-button>
                                </form>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
