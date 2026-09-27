<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Parámetros de aporte (§14)</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

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

            @if ($gestion)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-1">Aporte mensual — {{ $gestion->nombre }}</h3>
                    <p class="text-xs text-slate-500 mb-4">
                        Los valores iniciales confirmados (Bs 40, febrero a noviembre, día 10) son editables aquí:
                        no están fijos en el código. Guardar un cambio <strong>NO recalcula cuotas ya emitidas</strong>
                        ni reescribe pagos validados; solo afecta a cuotas que aún no se generaron.
                    </p>

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
        </div>
    </div>
</x-app-layout>
