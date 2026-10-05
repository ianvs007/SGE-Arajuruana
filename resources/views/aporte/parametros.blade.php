{{--
    Vista: Parámetros del aporte
    Aquí Administración configura, para cada gestión, cuánto se cobra de aporte
    mensual, entre qué meses y qué día vence cada cuota. Decidimos que estos
    valores se guarden en la base de datos y no en el código, para que puedan
    cambiar de un año a otro sin tocar el sistema.

    Variables que recibe del controlador:
    - $gestiones: lista de gestiones para el selector.
    - $gestion: gestión elegida (puede ser null si no hay ninguna).
    - $parametro: parámetros actuales de esa gestión.
    - $datosPago: QR y cuenta bancaria del colegio (banco, titular, cuenta, qr_ruta).
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Parámetros de aporte (§14)</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{-- Selector de gestión: al cambiarlo, el formulario se envía solo para recargar los parámetros --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label for="gestion" value="Gestión" />
                        <select id="gestion" name="gestion" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full"
                            onchange="this.form.submit()">
                            @foreach ($gestiones as $g)
                                <option value="{{ $g->id }}" @selected($gestion && $g->id === $gestion->id)>{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Ver</x-primary-button>
                </form>
            </div>

            {{-- El formulario solo se muestra si existe alguna gestión; si no, se invita a crear una --}}
            @if ($gestion)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-1">Aporte mensual — {{ $gestion->nombre }}</h3>
                    <p class="text-xs text-slate-500 mb-4">
                        Los valores iniciales confirmados (Bs 40, febrero a noviembre, día 10) son editables aquí:
                        no están fijos en el código. Guardar un cambio <strong>NO recalcula cuotas ya emitidas</strong>
                        ni reescribe pagos validados; solo afecta a cuotas que aún no se generaron.
                    </p>

                    {{--
                        Formulario de parámetros. Se envía con método PUT porque actualiza un registro
                        existente. Cambiar estos valores no recalcula cuotas ya emitidas.
                    --}}
                    <form method="POST" action="{{ route('aporte.parametros.update', $gestion) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="monto_mensual" value="Aporte mensual (Bs)" />
                                <x-text-input id="monto_mensual" type="number" step="0.01" min="0.01" name="monto_mensual"
                                    class="block mt-1 w-full" :value="old('monto_mensual', $parametro->monto_mensual)" required />
                                <x-input-error :messages="$errors->get('monto_mensual')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="dia_vencimiento" value="Día de vencimiento" />
                                <x-text-input id="dia_vencimiento" type="number" min="1" max="31" name="dia_vencimiento"
                                    class="block mt-1 w-full" :value="old('dia_vencimiento', $parametro->dia_vencimiento)" required />
                                <p class="text-xs text-slate-500 mt-1">Si el mes es más corto, vence el último día del mes.</p>
                                <x-input-error :messages="$errors->get('dia_vencimiento')" class="mt-2" />
                            </div>
                            {{-- Meses inicial y final del cobro; las opciones se generan con un bucle del 1 al 12 --}}
                            <div>
                                <x-input-label for="mes_inicio" value="Mes inicial" />
                                <select id="mes_inicio" name="mes_inicio" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" @selected((int) old('mes_inicio', $parametro->mes_inicio) === $m)>
                                            {{ \App\Models\CuotaAporte::NOMBRES_MES[$m] }}
                                        </option>
                                    @endfor
                                </select>
                                <x-input-error :messages="$errors->get('mes_inicio')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="mes_fin" value="Mes final" />
                                <select id="mes_fin" name="mes_fin" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" @selected((int) old('mes_fin', $parametro->mes_fin) === $m)>
                                            {{ \App\Models\CuotaAporte::NOMBRES_MES[$m] }}
                                        </option>
                                    @endfor
                                </select>
                                <x-input-error :messages="$errors->get('mes_fin')" class="mt-2" />
                            </div>
                        </div>

                        {{-- Casilla para activar o desactivar los parámetros de esta gestión --}}
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm"
                                @checked(old('activo', $parametro->activo ?? true))>
                            Parámetros activos para esta gestión
                        </label>

                        <div class="flex items-center gap-3">
                            <x-primary-button>Guardar parámetros</x-primary-button>
                            <a href="{{ route('aporte.cuotas.index', ['gestion' => $gestion->id]) }}">
                                <x-secondary-button type="button">Ver cuotas de esta gestión</x-secondary-button>
                            </a>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6 text-slate-500 text-sm">
                    No hay gestiones registradas. Cree una en <a class="text-sky-700 hover:underline" href="{{ route('gestiones.index') }}">Gestiones</a>.
                </div>
            @endif

            {{--
                Datos para el pago por QR: la imagen del QR fijo que entrega el banco y los
                datos de la cuenta del colegio. Las familias los ven en "Informar un pago".
                El formulario usa multipart/form-data porque envía un archivo.
            --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-1">Datos para el pago por QR</h3>
                <p class="text-xs text-slate-500 mb-4">
                    Suba la imagen del QR que le entregó el banco para la cuenta del colegio (JPG o PNG, máximo 2 MB)
                    y complete los datos de la cuenta. Las familias pagan escaneando este QR desde la aplicación de su banco.
                </p>

                <div class="grid sm:grid-cols-3 gap-6">
                    <div class="sm:col-span-1 flex flex-col items-center justify-center border border-dashed border-slate-300 rounded-lg p-3">
                        @if ($datosPago['qr_ruta'])
                            <img src="{{ route('aporte.qr_pago') }}" alt="QR de pago del colegio" class="w-48 h-auto">
                            <span class="text-xs text-emerald-700 mt-2">QR cargado</span>
                        @else
                            <span class="text-sm text-amber-700 text-center">Todavía no se cargó el QR del colegio.</span>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('aporte.datos_pago.update') }}" enctype="multipart/form-data" class="sm:col-span-2 space-y-4">
                        @csrf
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="banco" value="Banco" />
                                <x-text-input id="banco" name="banco" class="block mt-1 w-full" maxlength="100"
                                    :value="old('banco', $datosPago['banco'])" required />
                                <x-input-error :messages="$errors->get('banco')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cuenta" value="Número de cuenta" />
                                <x-text-input id="cuenta" name="cuenta" class="block mt-1 w-full" maxlength="60"
                                    :value="old('cuenta', $datosPago['cuenta'])" required />
                                <x-input-error :messages="$errors->get('cuenta')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="titular" value="Titular de la cuenta" />
                                <x-text-input id="titular" name="titular" class="block mt-1 w-full" maxlength="150"
                                    :value="old('titular', $datosPago['titular'])" required />
                                <x-input-error :messages="$errors->get('titular')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="qr" :value="$datosPago['qr_ruta'] ? 'Reemplazar imagen del QR (opcional)' : 'Imagen del QR'" />
                                <input id="qr" name="qr" type="file" accept="image/jpeg,image/png"
                                    class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700 hover:file:bg-slate-200"
                                    @required(! $datosPago['qr_ruta'])>
                                <x-input-error :messages="$errors->get('qr')" class="mt-2" />
                            </div>
                        </div>
                        <x-primary-button>Guardar datos de pago</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
