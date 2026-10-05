{{--
    Vista: Informar un pago (aviso de pago)
    Pantalla que usa el responsable familiar para pagar el aporte. Presenta las
    dos formas de pago del colegio:
    - Por QR: la familia paga escaneando el QR del colegio desde su banco, marca
      qué meses de qué hijo está pagando (cada mes puede pagarse completo o en
      parte), y sube el comprobante que le dio su banco. La deuda no cambia hasta
      que el personal verifique en su banco que el dinero ingresó.
    - En efectivo: la familia paga en secretaría y recibe allí su comprobante.

    Variables que recibe del controlador:
    - $cuotasPendientes: cuotas con saldo de los hijos del responsable.
    - $totalPendiente: suma en centavos de esas cuotas.
    - $datosPago: QR y cuenta bancaria del colegio (banco, titular, cuenta, qr_ruta).
    - $enAvisoPendiente: ids de cuotas que ya están en otro aviso pendiente.
--}}
<x-app-layout>
    {{-- Cabecera con el título y el botón para volver al listado de avisos --}}
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Pagar el aporte mensual</h2>
            <a href="{{ route('aporte.avisos.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            {{-- Mensajes de éxito o error que vienen de la sesión --}}
            @include('partials.flash')

            {{-- Las dos formas de pago, lado a lado --}}
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5 border-t-4 border-sky-500">
                    <h3 class="font-semibold text-slate-800 mb-2">Opción 1: pagar con QR</h3>
                    @if ($datosPago['qr_ruta'])
                        <div class="flex gap-4 items-start">
                            <img src="{{ route('aporte.qr_pago') }}" alt="QR de pago del colegio" class="w-36 h-auto border border-slate-200 rounded">
                            <dl class="text-sm space-y-1">
                                <div><dt class="text-slate-500 text-xs">Banco</dt><dd class="font-medium">{{ $datosPago['banco'] }}</dd></div>
                                <div><dt class="text-slate-500 text-xs">Titular</dt><dd class="font-medium">{{ $datosPago['titular'] }}</dd></div>
                                <div><dt class="text-slate-500 text-xs">Cuenta</dt><dd class="font-medium">{{ $datosPago['cuenta'] }}</dd></div>
                            </dl>
                        </div>
                        <ol class="list-decimal list-inside text-xs text-slate-600 mt-3 space-y-0.5">
                            <li>Marque abajo los meses que va a pagar; el sistema calcula el total.</li>
                            <li>Escanee el QR con la aplicación de su banco y pague <strong>exactamente ese total</strong>.</li>
                            <li>Suba el comprobante que le dio su banco y envíe el aviso.</li>
                        </ol>
                    @else
                        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                            El colegio todavía no publicó su QR de pago. Por ahora pague en efectivo en secretaría.
                        </p>
                    @endif
                </div>

                <div class="bg-white shadow-sm rounded-lg p-5 border-t-4 border-emerald-500">
                    <h3 class="font-semibold text-slate-800 mb-2">Opción 2: pagar en efectivo</h3>
                    <p class="text-sm text-slate-600">
                        Acérquese a la <strong>secretaría del colegio</strong> en horario de atención e indique qué meses
                        de qué hijo desea pagar (puede pagar un mes completo o una parte).
                    </p>
                    <p class="text-sm text-slate-600 mt-2">
                        El personal recibe y cuenta el dinero, registra el pago y le entrega un
                        <strong>comprobante interno impreso</strong>. En este caso no necesita llenar nada aquí.
                    </p>
                </div>
            </div>

            <div class="bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 rounded text-sm">
                <strong>Importante:</strong> al enviar el aviso la deuda <strong>todavía no cambia</strong>.
                El personal del colegio verifica en su banco que el dinero ingresó y recién entonces registra el pago
                de los meses que usted marcó. Podrá ver el estado del aviso en "Avisos de pago".
            </div>

            @if ($datosPago['qr_ruta'])
                {{--
                    Formulario del pago por QR. Usa multipart/form-data porque envía el comprobante.
                    Cada mes tiene una casilla y un monto: el monto empieza con el saldo completo y
                    puede bajarse para un pago parcial. Los campos de un mes no marcado quedan
                    deshabilitados y no se envían.
                --}}
                <form method="POST" action="{{ route('aporte.avisos.store') }}" enctype="multipart/form-data"
                    class="bg-white shadow-sm rounded-lg p-6 space-y-5" id="form-aviso">
                    @csrf
                    <h3 class="font-semibold text-slate-800">Informar un pago hecho con QR</h3>

                    <div>
                        <x-input-label value="1. ¿Qué meses está pagando?" />
                        @if ($cuotasPendientes->isEmpty())
                            <p class="text-sm text-slate-500 mt-2">Sus representados no tienen cuotas pendientes en la gestión actual.</p>
                        @else
                            <div class="mt-2 border border-slate-200 rounded-lg divide-y divide-slate-100">
                                @foreach ($cuotasPendientes as $cuota)
                                    @php
                                        $saldo = \App\Support\Dinero::aDecimal($cuota->saldoCentavos());
                                        $bloqueada = in_array($cuota->id, $enAvisoPendiente, true);
                                        $marcada = ! $bloqueada && old('cuotas.'.$cuota->id.'.cuota_id');
                                    @endphp
                                    <div class="flex flex-wrap items-center gap-3 px-3 py-2 {{ $bloqueada ? 'bg-slate-50 text-slate-400' : '' }}">
                                        <label class="flex items-center gap-2 flex-1 min-w-[14rem] text-sm">
                                            <input type="checkbox" class="rounded border-gray-300 text-sky-600 casilla-mes"
                                                name="cuotas[{{ $cuota->id }}][cuota_id]" value="{{ $cuota->id }}"
                                                data-monto="monto-{{ $cuota->id }}"
                                                @checked($marcada) @disabled($bloqueada)>
                                            <span>
                                                <strong>{{ $cuota->etiquetaPeriodo() }}</strong> — {{ $cuota->estudiante?->nombreCompleto() }}
                                                <span class="text-xs {{ $cuota->estaVencida() && ! $bloqueada ? 'text-rose-700' : 'text-slate-500' }}">
                                                    (saldo {{ \App\Support\Dinero::formato($cuota->saldoCentavos()) }}{{ $cuota->estaVencida() ? ', vencida' : '' }})
                                                </span>
                                                @if ($bloqueada)
                                                    <span class="block text-xs">Ya está en otro aviso pendiente de verificación.</span>
                                                @endif
                                            </span>
                                        </label>
                                        <div class="flex items-center gap-1 text-sm">
                                            <span class="text-slate-500">Bs</span>
                                            <input type="number" step="0.01" min="0.01" max="{{ $saldo }}"
                                                id="monto-{{ $cuota->id }}" name="cuotas[{{ $cuota->id }}][monto]"
                                                value="{{ old('cuotas.'.$cuota->id.'.monto', $saldo) }}"
                                                class="w-28 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-right monto-mes"
                                                @disabled(! $marcada) required>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Cada mes empieza con su saldo completo. Si paga solo una parte, escriba el monto que pagó para ese mes.
                            </p>
                        @endif
                        <x-input-error :messages="$errors->get('cuotas')" class="mt-2" />
                        <x-input-error :messages="collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'cuotas.'))->flatten()->unique()->all()" class="mt-2" />
                    </div>

                    <div class="flex justify-between items-center bg-slate-50 border border-slate-200 rounded-lg px-4 py-3">
                        <span class="text-sm font-medium text-slate-700">2. Total que debe pagar con el QR</span>
                        <span class="text-xl font-semibold text-slate-900" id="total-aviso">Bs 0,00</span>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="fecha_pago" value="3. Fecha en que pagó" />
                            <x-text-input id="fecha_pago" name="fecha_pago" type="date" class="block mt-1 w-full"
                                :value="old('fecha_pago', now()->toDateString())" max="{{ now()->toDateString() }}" required />
                            <x-input-error :messages="$errors->get('fecha_pago')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="comprobante" value="4. Comprobante del banco" />
                            <input id="comprobante" name="comprobante" type="file" accept=".jpg,.jpeg,.png,.pdf" required
                                class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700 hover:file:bg-slate-200">
                            <p class="text-xs text-slate-500 mt-1">Foto o captura (JPG, PNG) o PDF, máximo 5 MB.</p>
                            <x-input-error :messages="$errors->get('comprobante')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="nota" value="Nota (opcional)" />
                        <textarea id="nota" name="nota" rows="2" maxlength="2000"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full"
                            placeholder="Ej.: Pagué desde la cuenta de mi esposo.">{{ old('nota') }}</textarea>
                        <x-input-error :messages="$errors->get('nota')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button :disabled="$cuotasPendientes->isEmpty()">Enviar aviso de pago</x-primary-button>
                        <a href="{{ route('aporte.avisos.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>

                {{-- Habilita el monto de cada mes marcado y recalcula el total en centavos --}}
                <script>
                    (function () {
                        const form = document.getElementById('form-aviso');
                        const total = document.getElementById('total-aviso');
                        const formato = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        function recalcular() {
                            let centavos = 0;
                            form.querySelectorAll('.casilla-mes').forEach(function (casilla) {
                                const monto = document.getElementById(casilla.dataset.monto);
                                monto.disabled = ! casilla.checked;
                                if (casilla.checked) {
                                    centavos += Math.round((parseFloat(monto.value) || 0) * 100);
                                }
                            });
                            total.textContent = 'Bs ' + formato.format(centavos / 100);
                        }

                        form.addEventListener('change', recalcular);
                        form.addEventListener('input', recalcular);
                        recalcular();
                    })();
                </script>
            @endif
        </div>
    </div>
</x-app-layout>
