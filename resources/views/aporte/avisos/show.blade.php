{{--
    Vista: Detalle de un aviso de pago
    Muestra toda la información de un aviso: estado, monto declarado, nota del
    responsable y quién lo revisó. Desde aquí Administración valida el aviso
    repartiendo el monto entre las cuotas, o lo rechaza indicando el motivo.
    El responsable familiar puede anular su propio aviso mientras siga pendiente.

    Variables que recibe del controlador:
    - $aviso: el aviso de pago con sus relaciones (padre, revisor, pago).
    - $cuotasPorAlumno: cuotas con saldo de los hijos del responsable, agrupadas
      por estudiante; se usan en el formulario de validación.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Aviso {{ $aviso->referencia }}</h2>
            <a href="{{ route('aporte.avisos.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{--
                Encabezado del aviso: estado con su color, monto declarado y los datos
                principales. Definimos un arreglo de colores para cada estado posible.
            --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                @php($colores = ['pendiente' => 'bg-amber-100 text-amber-800', 'validado' => 'bg-emerald-100 text-emerald-800', 'rechazado' => 'bg-rose-100 text-rose-800', 'anulado' => 'bg-slate-200 text-slate-600'])
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-slate-500">Estado:</span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full font-semibold {{ $colores[$aviso->estado] ?? '' }}">{{ $aviso->nombreEstado() }}</span>
                    </div>
                    <div class="text-right text-sm">
                        <div class="text-slate-500 text-xs">Monto declarado</div>
                        <div class="text-xl font-semibold text-slate-800">{{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</div>
                    </div>
                </div>

                <dl class="mt-4 grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Informado por</dt>
                        <dd class="font-medium">{{ $aviso->padre?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Fecha del aviso</dt>
                        <dd class="font-medium">{{ optional($aviso->informado_en)->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Nota escrita del responsable</dt>
                        <dd class="font-medium whitespace-pre-wrap">{{ $aviso->nota ?: '— (sin nota) —' }}</dd>
                    </div>
                    {{-- El motivo solo tiene sentido si el aviso fue rechazado --}}
                    @if ($aviso->estado === 'rechazado')
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Motivo del rechazo</dt>
                            <dd class="font-medium text-rose-700">{{ $aviso->motivo_rechazo }}</dd>
                        </div>
                    @endif
                    {{-- Datos de la persona de Administración que revisó el aviso, si ya fue revisado --}}
                    @if ($aviso->revisado_por)
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Revisado por</dt>
                            <dd class="font-medium">{{ $aviso->revisor?->name }} · {{ optional($aviso->revisado_en)->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Si el aviso ya generó un pago validado, mostramos el enlace a su comprobante interno --}}
                @if ($aviso->pago && $aviso->pago->estaValidado())
                    <div class="mt-4 bg-emerald-50 border border-emerald-200 rounded p-3 text-sm">
                        Pago validado · comprobante interno
                        <a href="{{ route('aporte.pagos.show', $aviso->pago) }}" class="font-mono text-sky-700 hover:underline">{{ $aviso->pago->comprobante_numero }}</a>
                        por {{ \App\Support\Dinero::formato($aviso->pago->montoCentavos()) }}.
                    </div>
                @endif

                {{--
                    Botón para anular el aviso. Solo lo ve el mismo responsable que lo informó y
                    solo mientras esté pendiente; una vez revisado ya no se puede anular.
                --}}
                @if ($aviso->estaPendiente() && auth()->user()->can('aporte.avisos.informar') && $aviso->padre_id === auth()->id())
                    <form method="POST" action="{{ route('aporte.avisos.anular', $aviso) }}" class="mt-4"
                        onsubmit="return confirm('¿Anular este aviso pendiente?')">
                        @csrf
                        <x-danger-button>Anular mi aviso</x-danger-button>
                    </form>
                @endif
            </div>

            {{--
                Sección de validación. Solo la ve quien tiene permiso para gestionar avisos
                (Administración) y solo si el aviso sigue pendiente. Aquí se reparte el monto
                declarado entre las cuotas de uno o varios hijos.
            --}}
            @can('aporte.avisos.gestionar')
                @if ($aviso->estaPendiente())
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-semibold text-slate-800 mb-1">Validar y distribuir el pago</h3>
                        <p class="text-xs text-slate-500 mb-4">
                            Asigne el monto declarado a las cuotas correspondientes (varios hijos y meses).
                            La <strong>suma distribuida debe ser exactamente {{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</strong>;
                            cada aplicación no puede superar el saldo de su cuota. Al validar se crea el pago único
                            y el comprobante interno (transaccional, sin doble procesamiento).
                        </p>

                        {{-- Si no hay cuotas con saldo no se puede validar; se sugiere generar cuotas o rechazar --}}
                        @if ($cuotasPorAlumno->isEmpty())
                            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-sm">
                                Los representados de este responsable no tienen cuotas con saldo en la gestión del aviso.
                                Genere cuotas o rechace el aviso si no corresponde.
                            </div>
                        @else
                            <form method="POST" action="{{ route('aporte.avisos.validar', $aviso) }}" id="form-validar">
                                @csrf
                                {{--
                                    Por cada estudiante listamos sus cuotas con saldo. Cada cuota tiene una
                                    casilla para marcarla y un campo para el monto a aplicar; ambos inician
                                    deshabilitados y el script de abajo los activa al marcar la casilla, así
                                    solo se envían al servidor las cuotas seleccionadas.
                                --}}
                                <div class="space-y-5">
                                    @foreach ($cuotasPorAlumno as $alumnoId => $cuotasAlumno)
                                        <div>
                                            <h4 class="font-medium text-slate-700 mb-2 border-b border-slate-100 pb-1">
                                                {{ $cuotasAlumno->first()->estudiante?->nombreCompleto() }}
                                                <span class="text-xs text-slate-400">{{ $cuotasAlumno->first()->estudiante?->codigo }}</span>
                                            </h4>
                                            <div class="space-y-2">
                                                @foreach ($cuotasAlumno as $cuota)
                                                    <div class="flex flex-wrap items-center gap-3 text-sm">
                                                        <label class="flex items-center gap-2 w-64">
                                                            <input type="checkbox" class="cuota-check rounded border-gray-300 text-indigo-600"
                                                                data-cuota="{{ $cuota->id }}" data-saldo="{{ $cuota->saldoCentavos() }}">
                                                            <span>
                                                                {{ $cuota->etiquetaPeriodo() }}
                                                                <span class="text-xs text-slate-400">saldo {{ \App\Support\Dinero::formato($cuota->saldoCentavos()) }}</span>
                                                            </span>
                                                        </label>
                                                        <input type="hidden" name="aplicaciones[{{ $cuota->id }}][cuota_id]" value="{{ $cuota->id }}" class="cuota-id-input" data-for="{{ $cuota->id }}" disabled>
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

                                {{--
                                    Resumen de la distribución: monto del aviso frente a la suma asignada.
                                    El botón de validar empieza deshabilitado y solo se activa cuando ambas
                                    cantidades coinciden exactamente.
                                --}}
                                <div class="mt-5 border-t border-slate-200 pt-4">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-slate-600">Monto del aviso</span>
                                        <span class="font-medium" id="monto-objetivo" data-centavos="{{ $aviso->montoCentavos() }}">{{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm mb-3">
                                        <span class="text-slate-600">Suma distribuida</span>
                                        <span class="font-semibold" id="suma-distribuida">Bs 0,00</span>
                                    </div>
                                    <div id="aviso-suma" class="text-xs mb-3 hidden"></div>

                                    <div class="mb-3">
                                        <x-input-label for="observacion" value="Observación del operador (opcional)" />
                                        <x-text-input id="observacion" name="observacion" class="block mt-1 w-full" :value="old('observacion')" />
                                    </div>

                                    <x-primary-button id="btn-validar" disabled>Validar pago y emitir comprobante</x-primary-button>
                                </div>
                            </form>
                        @endif

                        <hr class="my-6 border-slate-200">

                        {{--
                            Formulario de rechazo: exige escribir el motivo para que el responsable sepa
                            por qué no se aceptó. Rechazar no modifica la deuda del estudiante.
                        --}}
                        <form method="POST" action="{{ route('aporte.avisos.rechazar', $aviso) }}" class="space-y-3"
                            onsubmit="return confirm('¿Rechazar este aviso? La deuda del alumno no cambiará.')">
                            @csrf
                            <h4 class="font-medium text-slate-700">Rechazar aviso</h4>
                            <div>
                                <x-input-label for="motivo_rechazo" value="Motivo del rechazo (obligatorio)" />
                                <x-text-input id="motivo_rechazo" name="motivo_rechazo" class="block mt-1 w-full" required />
                                <x-input-error :messages="$errors->get('motivo_rechazo')" class="mt-2" />
                            </div>
                            <x-danger-button>Rechazar aviso</x-danger-button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>

    {{--
        Script de apoyo para la distribución del pago. Solo se carga cuando el usuario
        puede validar y existen cuotas. Habilita los campos de las cuotas marcadas,
        recalcula la suma en centavos (para evitar errores de redondeo) y no deja
        enviar el formulario hasta que la suma sea igual al monto del aviso.
        De todas formas, el servidor vuelve a validar estas reglas.
    --}}
    @can('aporte.avisos.gestionar')
        @if ($aviso->estaPendiente() && $cuotasPorAlumno->isNotEmpty())
            <script>
                // Distribución en el cliente: habilita campos de cuotas marcadas,
                // recalcula la suma y exige que coincida EXACTAMENTE con el aviso
                // (la regla autoritativa también se valida en el servidor, §20.15).
                document.addEventListener('DOMContentLoaded', function () {
                    const objetivo = parseInt(document.getElementById('monto-objetivo').dataset.centavos, 10);
                    const sumaEl = document.getElementById('suma-distribuida');
                    const avisoEl = document.getElementById('aviso-suma');
                    const btn = document.getElementById('btn-validar');

                    const aCentavos = (v) => Math.round((parseFloat(v) || 0) * 100);
                    const formatoBs = (cent) => 'Bs ' + (cent / 100).toFixed(2).replace('.', ',');

                    function recalcular() {
                        let suma = 0;
                        let hayMarcada = false;
                        let excedeSaldo = false;

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
                                if (cent > parseInt(monto.dataset.saldoCent, 10) || cent <= 0) {
                                    excedeSaldo = true;
                                }
                            } else {
                                monto.disabled = true;
                                idInput.disabled = true;
                                monto.value = '';
                            }
                        });

                        sumaEl.textContent = formatoBs(suma);
                        avisoEl.classList.remove('hidden');

                        let ok = false;
                        if (!hayMarcada) {
                            avisoEl.textContent = 'Marque al menos una cuota.';
                            avisoEl.className = 'text-xs mb-3 text-slate-500';
                        } else if (excedeSaldo) {
                            avisoEl.textContent = 'Cada aplicación debe ser positiva y no superar el saldo de su cuota.';
                            avisoEl.className = 'text-xs mb-3 text-rose-600';
                        } else if (suma === objetivo) {
                            avisoEl.textContent = '✓ La suma distribuida coincide con el monto del aviso.';
                            avisoEl.className = 'text-xs mb-3 text-emerald-600';
                            ok = true;
                        } else {
                            avisoEl.textContent = 'La suma distribuida (' + formatoBs(suma) + ') debe ser exactamente ' +
                                formatoBs(objetivo) + '. No se admite excedente ni saldo a favor automático.';
                            avisoEl.className = 'text-xs mb-3 text-rose-600';
                        }

                        btn.disabled = !ok;
                    }

                    document.querySelectorAll('.cuota-check').forEach((c) => c.addEventListener('change', recalcular));
                    document.querySelectorAll('.cuota-monto').forEach((m) => m.addEventListener('input', recalcular));

                    document.getElementById('form-validar').addEventListener('submit', function (e) {
                        if (btn.disabled) {
                            e.preventDefault();
                            alert('La suma distribuida debe ser exactamente el monto del aviso.');
                        }
                    });
                });
            </script>
        @endif
    @endcan
</x-app-layout>
