{{--
    Vista: Registrar pago (QR + WhatsApp). Flujo económico anterior.
    Pantalla donde el padre de familia elige uno de sus cargos pendientes (por ejemplo, un
    cargo extraordinario) e indica el monto que va a pagar. Al enviarla, el sistema genera el
    pago con un código QR y un enlace de WhatsApp para mandar el comprobante a tesorería.
    Recibe del controlador:
      - $cargos: cargos del padre que todavía tienen saldo pendiente.
      - $cargoSeleccionado: cargo elegido de antemano (puede ser null).
      - $whatsapp: número de WhatsApp de tesorería.
    La usan los padres o responsables de los estudiantes.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar pago (QR + WhatsApp)</h2>
    </x-slot>
    <div class="py-8"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        @include('partials.flash')
        <div class="bg-white shadow-sm rounded-lg p-6">
            {{-- Si el padre no tiene deudas pendientes, solo se muestra un mensaje y no el formulario. --}}
            @if ($cargos->isEmpty())
                <p class="text-gray-600">No tiene cargos pendientes por pagar.</p>
            @else
                <form method="POST" action="{{ route('pagos.store') }}" class="space-y-4">
                    @csrf
                    {{-- Lista de cargos con el saldo pendiente de cada uno, en bolivianos. --}}
                    <div>
                        <x-input-label value="Cargo a pagar" />
                        <select name="cargo_id" class="border-gray-300 rounded-md shadow-sm mt-1 w-full" required>
                            @foreach ($cargos as $cargo)
                                <option value="{{ $cargo->id }}" @selected(old('cargo_id', $cargoSeleccionado?->id) == $cargo->id)>
                                    {{ $cargo->concepto }} — pendiente Bs {{ number_format($cargo->montoPendiente(), 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Monto a pagar. Si ya hay un cargo seleccionado, se propone su saldo pendiente completo. --}}
                    <div>
                        <x-input-label value="Monto a pagar (Bs)" />
                        <x-text-input type="number" step="0.01" name="monto" class="block mt-1 w-full"
                            :value="old('monto', $cargoSeleccionado ? number_format($cargoSeleccionado->montoPendiente(), 2, '.', '') : '')" required />
                        <x-input-error :messages="$errors->get('monto')" class="mt-2" />
                    </div>
                    {{-- Nota opcional para que tesorería identifique mejor el comprobante. --}}
                    <div>
                        <x-input-label value="Nota del comprobante (opcional)" />
                        <x-text-input name="comprobante_nota" class="block mt-1 w-full" :value="old('comprobante_nota')" placeholder="Ej. transferencia desde banco X" />
                    </div>
                    <p class="text-sm text-gray-600">WhatsApp de tesorería: <strong>{{ $whatsapp }}</strong>. Tras generar el pago verá el QR y el enlace para enviar el comprobante.</p>
                    <x-primary-button>Generar pago y QR</x-primary-button>
                </form>
            @endif
        </div>
    </div></div>
</x-app-layout>
