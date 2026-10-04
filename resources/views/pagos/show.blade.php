{{--
    Vista: Detalle de un pago (QR + WhatsApp). Flujo económico anterior.
    Muestra los datos del pago, el código QR para realizarlo y un botón para enviar el
    comprobante por WhatsApp a tesorería. Si el usuario es de tesorería y el pago sigue
    pendiente, también aparecen los formularios para confirmarlo o rechazarlo.
    Recibe del controlador:
      - $pago: el pago con su cargo y estudiante.
      - $whatsapp: número de WhatsApp de tesorería.
      - $mensajeWa: texto del mensaje de WhatsApp ya codificado para la URL.
      - $qrSvg: el código QR generado como imagen SVG.
    La usan el padre que hizo el pago y el personal de tesorería.
--}}
<x-app-layout>
    {{-- El título muestra la referencia única del pago, la misma que se envía con el comprobante. --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pago {{ $pago->referencia }}</h2>
    </x-slot>
    <div class="py-8"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @include('partials.flash')
        <div class="bg-white shadow-sm rounded-lg p-6 grid md:grid-cols-2 gap-6">
            {{-- Columna izquierda: datos del pago. --}}
            <div class="text-sm space-y-2">
                <div><span class="text-gray-500">Concepto:</span> {{ $pago->cargo?->concepto }}</div>
                <div><span class="text-gray-500">Estudiante:</span> {{ $pago->cargo?->estudiante?->nombreCompleto() ?: '—' }}</div>
                <div><span class="text-gray-500">Monto:</span> Bs {{ number_format($pago->monto, 2) }}</div>
                <div><span class="text-gray-500">Estado:</span> {{ $pago->estado }}</div>
                <div><span class="text-gray-500">Método:</span> {{ $pago->metodo }}</div>
                <div><span class="text-gray-500">WhatsApp destino:</span> {{ $whatsapp }}</div>
                {{-- La observación de tesorería solo aparece si se registró alguna (por ejemplo, el motivo de un rechazo). --}}
                @if ($pago->observacion_operador)
                    <div><span class="text-gray-500">Obs. operador:</span> {{ $pago->observacion_operador }}</div>
                @endif
                {{--
                    Botón de WhatsApp: arma un enlace wa.me con el número de tesorería (dejando solo los
                    dígitos con preg_replace) y el mensaje prellenado con los datos del pago.
                --}}
                <div class="pt-3">
                    <a class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm rounded-md"
                       href="https://wa.me/{{ preg_replace('/\D/', '', $whatsapp) }}?text={{ $mensajeWa }}"
                       target="_blank" rel="noopener">
                        Enviar comprobante por WhatsApp
                    </a>
                </div>
                <p class="text-xs text-gray-500 mt-2">Escanee o muestre el QR, realice el pago y envíe la captura del comprobante por WhatsApp con la referencia.</p>
            </div>
            {{--
                Columna derecha: código QR. Se imprime con {!! !!} (sin escapar) porque es código SVG
                generado por el propio sistema; debajo se muestra el contenido del QR en texto.
            --}}
            <div class="flex flex-col items-center justify-center border rounded-lg p-4 bg-gray-50">
                <div class="bg-white p-3 rounded">{!! $qrSvg !!}</div>
                <div class="mt-2 text-xs text-gray-600 break-all text-center">{{ $pago->qr_payload }}</div>
            </div>
        </div>

        {{--
            Panel de tesorería: solo para usuarios con el permiso pagos.confirmar y solo mientras el
            pago esté pendiente o en revisión. Para rechazar es obligatorio indicar el motivo.
        --}}
        @can('pagos.confirmar')
            @if (in_array($pago->estado, ['pendiente', 'en_revision']))
                <div class="bg-white shadow-sm rounded-lg p-6 grid md:grid-cols-2 gap-4">
                    <form method="POST" action="{{ route('pagos.confirmar', $pago) }}" class="space-y-3">
                        @csrf
                        <h3 class="font-semibold">Confirmar pago</h3>
                        <textarea name="observacion_operador" rows="2" class="border-gray-300 rounded-md shadow-sm w-full" placeholder="Observación (opcional)"></textarea>
                        <x-primary-button>Confirmar</x-primary-button>
                    </form>
                    <form method="POST" action="{{ route('pagos.rechazar', $pago) }}" class="space-y-3">
                        @csrf
                        <h3 class="font-semibold">Rechazar pago</h3>
                        <textarea name="observacion_operador" rows="2" class="border-gray-300 rounded-md shadow-sm w-full" placeholder="Motivo del rechazo" required></textarea>
                        <x-danger-button>Rechazar</x-danger-button>
                    </form>
                </div>
            @endif
        @endcan
    </div></div>
</x-app-layout>
