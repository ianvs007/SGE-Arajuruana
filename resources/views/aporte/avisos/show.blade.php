{{--
    Vista: Detalle de un aviso de pago
    Muestra toda la información de un aviso: estado, total, fecha del pago,
    meses que declara pagar la familia y el comprobante que subió.

    Desde aquí el operador (Administración, Director o Coordinadora):
    1. Revisa el comprobante y los meses declarados.
    2. Entra a la plataforma de SU banco (fuera del sistema) y verifica que el
       dinero ingresó a la cuenta del colegio.
    3. Marca la casilla de verificación, escribe el número de operación bancaria
       y valida: recién ahí se descuenta la deuda de esos meses.
    Si el pago no ingresó o algo no coincide, rechaza el aviso con un motivo.
    El responsable familiar puede anular su propio aviso mientras siga pendiente,
    y nadie puede validar un aviso que informó él mismo.

    Los avisos antiguos (sin meses declarados) conservan el formulario en el que
    el operador reparte el monto entre las cuotas.

    Variables que recibe del controlador:
    - $aviso: el aviso de pago con sus relaciones (padre, revisor, pago, meses declarados).
    - $cuotasPorAlumno: solo para avisos antiguos, cuotas con saldo de los hijos
      del responsable agrupadas por estudiante.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Aviso {{ $aviso->referencia }}</h2>
            <a href="{{ route('aporte.avisos.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    @php
        $declarados = $aviso->cuotasDeclaradas;
        $puedeValidar = auth()->user()->can('aporte.avisos.gestionar') && $aviso->estaPendiente();
        $esPropio = $aviso->padre_id === auth()->id();
        // Meses declarados que ya no pueden cobrarse por el monto declarado
        // (por ejemplo, porque se pagaron en efectivo mientras el aviso esperaba).
        $mesesConflicto = $declarados->filter(fn ($linea) => ! $linea->cuota
            || ! in_array($linea->cuota->estado, ['pendiente', 'parcial'], true)
            || $linea->cuota->saldoCentavos() < $linea->montoCentavos());
        $erroresValidacion = collect(['aviso', 'verificado_banco', 'operacion_bancaria', 'aplicaciones', 'monto'])
            ->flatMap(fn ($campo) => $errors->get($campo))->all();
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{--
                Encabezado del aviso: estado con su color, total y los datos
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
                        <div class="text-slate-500 text-xs">{{ $declarados->isNotEmpty() ? 'Total pagado con QR' : 'Monto declarado' }}</div>
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
                    @if ($aviso->fecha_pago)
                        <div>
                            <dt class="text-slate-500">Fecha en que dice haber pagado</dt>
                            <dd class="font-medium">{{ $aviso->fecha_pago->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Nota del responsable</dt>
                        <dd class="font-medium whitespace-pre-wrap">{{ $aviso->nota ?: '— (sin nota) —' }}</dd>
                    </div>
                    {{-- El motivo solo tiene sentido si el aviso fue rechazado --}}
                    @if ($aviso->estado === 'rechazado')
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Motivo del rechazo</dt>
                            <dd class="font-medium text-rose-700">{{ $aviso->motivo_rechazo }}</dd>
                        </div>
                    @endif
                    {{-- Datos de la persona que revisó el aviso, si ya fue revisado --}}
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
                        por {{ \App\Support\Dinero::formato($aviso->pago->montoCentavos()) }}
                        @if ($aviso->pago->operacion_bancaria)
                            · operación bancaria <span class="font-mono">{{ $aviso->pago->operacion_bancaria }}</span>
                        @endif
                    </div>
                @endif

                {{--
                    Botón para anular el aviso. Solo lo ve el mismo responsable que lo informó y
                    solo mientras esté pendiente; una vez revisado ya no se puede anular.
                --}}
                @if ($aviso->estaPendiente() && auth()->user()->can('aporte.avisos.informar') && $esPropio)
                    <form method="POST" action="{{ route('aporte.avisos.anular', $aviso) }}" class="mt-4"
                        onsubmit="return confirm('¿Anular este aviso pendiente?')">
                        @csrf
                        <x-danger-button>Anular mi aviso</x-danger-button>
                    </form>
                @endif
            </div>

            {{-- Meses que la familia declara pagar con este aviso --}}
            @if ($declarados->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Meses que está pagando</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2">Estudiante</th>
                                <th class="py-2">Mes</th>
                                <th class="py-2 text-right">Monto declarado</th>
                                @if ($aviso->estaPendiente())
                                    <th class="py-2 text-right">Saldo actual</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($declarados as $linea)
                                <tr class="{{ $aviso->estaPendiente() && $mesesConflicto->contains($linea) ? 'bg-rose-50' : '' }}">
                                    <td class="py-2">{{ $linea->cuota?->estudiante?->nombreCompleto() }}</td>
                                    <td class="py-2">{{ $linea->cuota?->etiquetaPeriodo() }}</td>
                                    <td class="py-2 text-right font-medium">
                                        {{ \App\Support\Dinero::formato($linea->montoCentavos()) }}
                                        @if ($linea->cuota && $linea->montoCentavos() < $linea->cuota->montoCentavos())
                                            <span class="text-[11px] text-slate-500">(parcial)</span>
                                        @endif
                                    </td>
                                    @if ($aviso->estaPendiente())
                                        <td class="py-2 text-right">{{ $linea->cuota ? \App\Support\Dinero::formato($linea->cuota->saldoCentavos()) : '—' }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 font-semibold">
                            <tr>
                                <td class="py-2" colspan="2">Total</td>
                                <td class="py-2 text-right">{{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</td>
                                @if ($aviso->estaPendiente())
                                    <td></td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            {{-- Comprobante subido por la familia; se sirve por una ruta protegida --}}
            @if ($aviso->tieneComprobante())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex justify-between items-center mb-3 gap-3">
                        <h3 class="font-semibold text-slate-800">Comprobante del banco</h3>
                        <a href="{{ route('aporte.avisos.comprobante', $aviso) }}" target="_blank" rel="noopener"
                            class="text-sm text-sky-700 hover:underline">Abrir en otra pestaña</a>
                    </div>
                    @if ($aviso->comprobanteEsImagen())
                        <img src="{{ route('aporte.avisos.comprobante', $aviso) }}" alt="Comprobante del aviso {{ $aviso->referencia }}"
                            class="max-h-[32rem] w-auto mx-auto border border-slate-200 rounded">
                    @else
                        <p class="text-sm text-slate-600">
                            El comprobante es un documento PDF.
                            <a href="{{ route('aporte.avisos.comprobante', $aviso) }}" target="_blank" rel="noopener" class="text-sky-700 hover:underline">Ver el PDF</a>.
                        </p>
                    @endif
                    <p class="text-xs text-slate-400 mt-2">El comprobante es una referencia: el pago solo se confirma verificándolo en la plataforma del banco.</p>
                </div>
            @endif

            {{--
                Sección de validación. Solo la ve quien puede gestionar avisos y solo si el
                aviso sigue pendiente. Quien informó el aviso no puede validarlo.
            --}}
            @if ($puedeValidar)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    @if ($esPropio)
                        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-sm">
                            Usted informó este aviso, por eso no puede validarlo ni rechazarlo. Debe revisarlo otro operador.
                        </div>
                    @else
                        <h3 class="font-semibold text-slate-800 mb-1">Verificar el pago en el banco y validarlo</h3>

                        @if ($erroresValidacion)
                            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded p-3 text-sm my-3">
                                <ul class="list-disc list-inside">
                                    @foreach ($erroresValidacion as $mensaje)
                                        <li>{{ $mensaje }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <ol class="list-decimal list-inside text-sm text-slate-600 space-y-1 mb-4">
                            <li>Revise el comprobante y los meses declarados.</li>
                            <li>Ingrese a la <strong>plataforma de su banco</strong> (fuera de este sistema) y confirme que ingresaron
                                <strong>{{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</strong> a la cuenta del colegio
                                @if ($aviso->fecha_pago) alrededor del {{ $aviso->fecha_pago->format('d/m/Y') }}@endif.</li>
                            <li>Si el dinero ingresó, marque la casilla, copie el número de operación del banco y valide.
                                Si no ingresó o no coincide, rechace el aviso explicando el motivo.</li>
                        </ol>

                        @if ($declarados->isNotEmpty())
                            {{-- Aviso con meses declarados: se validan exactamente esos meses --}}
                            @if ($mesesConflicto->isNotEmpty())
                                <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded p-3 text-sm">
                                    Algún mes declarado ya no tiene saldo suficiente (por ejemplo, se pagó en efectivo mientras este aviso esperaba).
                                    No se puede validar: rechace el aviso explicando el motivo para que la familia informe de nuevo.
                                </div>
                            @else
                                <form method="POST" action="{{ route('aporte.avisos.validar', $aviso) }}" class="space-y-4"
                                    onsubmit="return confirm('¿Confirma que verificó en el banco el ingreso de {{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}?')">
                                    @csrf
                                    @include('aporte.avisos.partials.verificacion-banco')
                                    <x-primary-button>Validar pago y emitir comprobante</x-primary-button>
                                </form>
                            @endif
                        @elseif ($cuotasPorAlumno->isEmpty())
                            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-sm">
                                Los representados de este responsable no tienen cuotas con saldo en la gestión del aviso.
                                Genere cuotas o rechace el aviso si no corresponde.
                            </div>
                        @else
                            {{--
                                Aviso antiguo sin meses declarados: el operador reparte el monto entre las
                                cuotas. La suma debe ser exactamente el monto del aviso y cada aplicación no
                                puede superar el saldo de su cuota.
                            --}}
                            <form method="POST" action="{{ route('aporte.avisos.validar', $aviso) }}" id="form-validar">
                                @csrf
                                <p class="text-xs text-slate-500 mb-4">
                                    Este aviso no indica los meses. Asigne el monto a las cuotas correspondientes:
                                    la <strong>suma distribuida debe ser exactamente {{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</strong>.
                                </p>
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

                                <div class="mt-5 border-t border-slate-200 pt-4 space-y-4">
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-slate-600">Monto del aviso</span>
                                            <span class="font-medium" id="monto-objetivo" data-centavos="{{ $aviso->montoCentavos() }}">{{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-600">Suma distribuida</span>
                                            <span class="font-semibold" id="suma-distribuida">Bs 0,00</span>
                                        </div>
                                        <div id="aviso-suma" class="text-xs mt-1 hidden"></div>
                                    </div>
                                    @include('aporte.avisos.partials.verificacion-banco')
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
                                <x-text-input id="motivo_rechazo" name="motivo_rechazo" class="block mt-1 w-full" required
                                    placeholder="Ej.: El dinero no ingresó a la cuenta del colegio / el monto no coincide." />
                                <x-input-error :messages="$errors->get('motivo_rechazo')" class="mt-2" />
                            </div>
                            <x-danger-button>Rechazar aviso</x-danger-button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{--
        Script de apoyo para repartir un aviso antiguo. Habilita los campos de las
        cuotas marcadas, recalcula la suma en centavos (para evitar errores de
        redondeo) y no deja enviar el formulario hasta que la suma sea igual al
        monto del aviso. De todas formas, el servidor vuelve a validar estas reglas.
    --}}
    @if ($puedeValidar && ! $esPropio && $declarados->isEmpty() && $cuotasPorAlumno->isNotEmpty())
        <script>
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
                        avisoEl.className = 'text-xs mt-1 text-slate-500';
                    } else if (excedeSaldo) {
                        avisoEl.textContent = 'Cada aplicación debe ser positiva y no superar el saldo de su cuota.';
                        avisoEl.className = 'text-xs mt-1 text-rose-600';
                    } else if (suma === objetivo) {
                        avisoEl.textContent = '✓ La suma distribuida coincide con el monto del aviso.';
                        avisoEl.className = 'text-xs mt-1 text-emerald-600';
                        ok = true;
                    } else {
                        avisoEl.textContent = 'La suma distribuida (' + formatoBs(suma) + ') debe ser exactamente ' +
                            formatoBs(objetivo) + '. No se admite excedente ni saldo a favor automático.';
                        avisoEl.className = 'text-xs mt-1 text-rose-600';
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
</x-app-layout>
