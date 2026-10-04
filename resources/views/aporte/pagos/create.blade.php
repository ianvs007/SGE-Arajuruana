{{--
    Vista: Registrar pago en ventanilla
    La usa Administración cuando un responsable familiar paga en persona en la
    unidad educativa, sin haber informado antes un aviso. Se elige al responsable,
    se escribe el monto recibido y se reparte entre las cuotas de sus hijos.
    Al guardar se emite el comprobante interno.

    Variables que recibe del controlador:
    - $responsables: usuarios con rol de responsable familiar.
    - $cuotasPorAlumno: cuotas con saldo de la gestión actual, agrupadas por estudiante.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Registrar pago en ventanilla (§14)</h2>
            <a href="{{ route('aporte.pagos.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            {{-- Instrucciones para el operador sobre cómo se debe distribuir el monto --}}
            <div class="bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 rounded text-sm">
                Registro directo del dinero recibido en ventanilla (sin aviso previo). La distribución entre
                cuotas de los hijos y meses la decide Administración; la <strong>suma distribuida debe ser
                exactamente el monto recibido</strong>. Al registrar se emite el comprobante interno (sin valor fiscal).
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('aporte.pagos.store') }}" id="form-ventanilla" class="space-y-5">
                    @csrf

                    {{-- Datos principales: quién paga y cuánto dinero se recibió --}}
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="padre_id" value="Responsable familiar que paga" />
                            <select id="padre_id" name="padre_id" required
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                                <option value="">Seleccione…</option>
                                @foreach ($responsables as $responsable)
                                    <option value="{{ $responsable->id }}" @selected((string) old('padre_id') === (string) $responsable->id)>
                                        {{ $responsable->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('padre_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="monto" value="Monto recibido (Bs)" />
                            <x-text-input id="monto" name="monto" type="number" step="0.01" min="0.01"
                                class="block mt-1 w-full" :value="old('monto')" required />
                            <x-input-error :messages="$errors->get('monto')" class="mt-2" />
                        </div>
                    </div>

                    {{-- Si no existen cuotas con saldo no hay a qué aplicar el pago, así que se pide generarlas primero --}}
                    @if ($cuotasPorAlumno->isEmpty())
                        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-sm">
                            No hay cuotas con saldo en la gestión actual. Genere las cuotas primero desde
                            <a class="underline" href="{{ route('aporte.cuotas.index') }}">Cuotas</a>.
                        </div>
                    @else
                        {{--
                            Grupos de cuotas por estudiante. En data-responsables guardamos los ids de los
                            responsables de cada alumno, para que el script muestre solo los hijos del
                            responsable seleccionado. Los campos inician deshabilitados y se activan al
                            marcar la casilla de la cuota.
                        --}}
                        <div id="grupos-cuotas" class="space-y-5">
                            @foreach ($cuotasPorAlumno as $alumnoId => $cuotasAlumno)
                                @php($alumno = $cuotasAlumno->first()->estudiante)
                                <div class="grupo-alumno" data-responsables="{{ $alumno?->responsables()->pluck('users.id')->implode(',') }}">
                                    <h4 class="font-medium text-slate-700 mb-2 border-b border-slate-100 pb-1">
                                        {{ $alumno?->nombreCompleto() }}
                                        <span class="text-xs text-slate-400">{{ $alumno?->codigo }}</span>
                                    </h4>
                                    <div class="space-y-2">
                                        @foreach ($cuotasAlumno as $cuota)
                                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                                <label class="flex items-center gap-2 w-64">
                                                    <input type="checkbox" class="cuota-check rounded border-gray-300 text-indigo-600"
                                                        data-cuota="{{ $cuota->id }}">
                                                    <span>
                                                        {{ $cuota->etiquetaPeriodo() }}
                                                        <span class="text-xs text-slate-400">saldo {{ \App\Support\Dinero::formato($cuota->saldoCentavos()) }}</span>
                                                        @if ($cuota->estaVencida())
                                                            <span class="text-[11px] bg-rose-100 text-rose-800 rounded px-1.5 py-0.5">vencida</span>
                                                        @endif
                                                    </span>
                                                </label>
                                                <input type="hidden" name="aplicaciones[{{ $cuota->id }}][cuota_id]" value="{{ $cuota->id }}"
                                                    class="cuota-id-input" data-for="{{ $cuota->id }}" disabled>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-slate-500 text-xs">Bs</span>
                                                    <input type="number" step="0.01" min="0" max="{{ $cuota->saldo }}"
                                                        name="aplicaciones[{{ $cuota->id }}][monto]"
                                                        class="cuota-monto border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-28 text-right"
                                                        data-for="{{ $cuota->id }}" data-saldo-cent="{{ $cuota->saldoCentavos() }}" disabled>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Suma distribuida, mensajes de ayuda y botón que se habilita solo si todo cuadra --}}
                        <div class="border-t border-slate-200 pt-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-slate-600">Suma distribuida</span>
                                <span class="font-semibold" id="suma-distribuida">Bs 0,00</span>
                            </div>
                            <div id="aviso-suma" class="text-xs mb-3 text-slate-500">Seleccione el responsable, marque cuotas y asigne montos.</div>

                            <div class="mb-3">
                                <x-input-label for="observacion" value="Observación (opcional)" />
                                <x-text-input id="observacion" name="observacion" class="block mt-1 w-full" :value="old('observacion')" />
                            </div>

                            <x-primary-button id="btn-registrar" disabled>Registrar pago y emitir comprobante</x-primary-button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>

    {{--
        Script del formulario de ventanilla. Al elegir un responsable muestra solo las
        cuotas de sus hijos, habilita los montos de las cuotas marcadas y compara la suma
        con el monto recibido. Mientras no coincidan exactamente, el botón queda
        deshabilitado. El servidor vuelve a comprobar todo al guardar.
    --}}
    @if ($cuotasPorAlumno->isNotEmpty())
        <script>
            // Ventanilla: filtra alumnos por el responsable elegido, habilita las
            // cuotas marcadas y exige suma distribuida == monto recibido (regla
            // autoritativa también en el servidor, §20.15).
            document.addEventListener('DOMContentLoaded', function () {
                const selectPadre = document.getElementById('padre_id');
                const montoEl = document.getElementById('monto');
                const sumaEl = document.getElementById('suma-distribuida');
                const avisoEl = document.getElementById('aviso-suma');
                const btn = document.getElementById('btn-registrar');

                const aCentavos = (v) => Math.round((parseFloat(v) || 0) * 100);
                const formatoBs = (cent) => 'Bs ' + (cent / 100).toFixed(2).replace('.', ',');

                function filtrarGrupos() {
                    const padreId = selectPadre.value;
                    document.querySelectorAll('.grupo-alumno').forEach(function (grupo) {
                        const ids = (grupo.dataset.responsables || '').split(',').filter(Boolean);
                        const visible = padreId !== '' && ids.includes(padreId);
                        grupo.style.display = visible ? '' : 'none';
                        if (!visible) {
                            // Desmarca cuotas de alumnos ocultos (no se pueden aplicar).
                            grupo.querySelectorAll('.cuota-check').forEach(function (check) {
                                check.checked = false;
                            });
                        }
                    });
                }

                function recalcular() {
                    const objetivo = aCentavos(montoEl.value);
                    let suma = 0;
                    let hayMarcada = false;
                    let error = false;

                    document.querySelectorAll('.cuota-check').forEach(function (check) {
                        const id = check.dataset.cuota;
                        const monto = document.querySelector('.cuota-monto[data-for="' + id + '"]');
                        const idInput = document.querySelector('.cuota-id-input[data-for="' + id + '"]');
                        if (check.checked) {
                            hayMarcada = true;
                            monto.disabled = false;
                            idInput.disabled = false;
                            const cent = aCentavos(monto.value);
                            suma += cent;
                            if (cent <= 0 || cent > parseInt(monto.dataset.saldoCent, 10)) {
                                error = true;
                            }
                        } else {
                            monto.disabled = true;
                            idInput.disabled = true;
                            monto.value = '';
                        }
                    });

                    sumaEl.textContent = formatoBs(suma);

                    let ok = false;
                    let clase = 'text-xs mb-3 text-slate-500';
                    if (selectPadre.value === '') {
                        avisoEl.textContent = 'Seleccione el responsable familiar que paga.';
                    } else if (objetivo <= 0) {
                        avisoEl.textContent = 'Ingrese el monto recibido.';
                    } else if (!hayMarcada) {
                        avisoEl.textContent = 'Marque al menos una cuota.';
                    } else if (error) {
                        avisoEl.textContent = 'Cada aplicación debe ser positiva y no superar el saldo de su cuota.';
                        clase = 'text-xs mb-3 text-rose-600';
                    } else if (suma === objetivo) {
                        avisoEl.textContent = '✓ La suma distribuida coincide con el monto recibido.';
                        clase = 'text-xs mb-3 text-emerald-600';
                        ok = true;
                    } else {
                        avisoEl.textContent = 'La suma distribuida (' + formatoBs(suma) + ') debe ser exactamente el monto recibido (' +
                            formatoBs(objetivo) + '). No se admite excedente ni saldo a favor automático.';
                        clase = 'text-xs mb-3 text-rose-600';
                    }
                    avisoEl.className = clase;

                    btn.disabled = !ok;
                }

                selectPadre.addEventListener('change', function () { filtrarGrupos(); recalcular(); });
                montoEl.addEventListener('input', recalcular);
                document.querySelectorAll('.cuota-check').forEach((c) => c.addEventListener('change', recalcular));
                document.querySelectorAll('.cuota-monto').forEach((m) => m.addEventListener('input', recalcular));

                document.getElementById('form-ventanilla').addEventListener('submit', function (e) {
                    if (btn.disabled) {
                        e.preventDefault();
                        alert('Revise el responsable, el monto y la distribución: la suma debe coincidir exactamente.');
                    }
                });

                filtrarGrupos();
                recalcular();
            });
        </script>
    @endif
</x-app-layout>
