<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Informar un pago (§14)</h2>
            <a href="{{ route('aporte.avisos.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            <div class="bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 rounded text-sm">
                <strong>Cómo funciona:</strong> usted informa el pago realizado con una <strong>nota escrita</strong>
                (no se adjuntan archivos ni imágenes). Administración lo validará contra el depósito real;
                <strong>la deuda no cambia hasta la validación</strong>. No se genera comprobante con el aviso pendiente.
            </div>

            @if ($cuotasPendientes->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3 text-sm">Cuotas pendientes de sus representados</h3>
                    <ul class="text-sm divide-y divide-slate-100 mb-3">
                        @foreach ($cuotasPendientes as $cuota)
                            <li class="py-1.5 flex justify-between gap-2">
                                <span>{{ $cuota->estudiante?->nombreCompleto() }} — {{ $cuota->etiquetaPeriodo() }}</span>
                                <span class="font-medium {{ $cuota->estaVencida() ? 'text-rose-700' : '' }}">
                                    {{ \App\Support\Dinero::formato($cuota->saldoCentavos()) }}
                                    @if ($cuota->estaVencida())<span class="text-[11px]">vencida</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex justify-between text-sm font-semibold border-t border-slate-200 pt-2">
                        <span>Total pendiente</span>
                        <span>{{ \App\Support\Dinero::formato($totalPendiente) }}</span>
                    </div>
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6 text-sm text-slate-500">
                    No hay cuotas pendientes registradas en la gestión actual. De todos modos puede informar un pago
                    (anticipos y cuotas atrasadas son válidos, §14).
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('aporte.avisos.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="monto_declarado" value="Monto pagado (Bs)" />
                        <x-text-input id="monto_declarado" name="monto_declarado" type="number" step="0.01" min="0.01"
                            class="block mt-1 w-full" :value="old('monto_declarado')" required />
                        <p class="text-xs text-slate-500 mt-1">
                            Puede ser un abono parcial, un anticipo o el total de varios meses/hijos:
                            Administración distribuirá el pago al validarlo.
                        </p>
                        <x-input-error :messages="$errors->get('monto_declarado')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="nota" value="Nota escrita (opcional)" />
                        <textarea id="nota" name="nota" rows="4" maxlength="2000"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full"
                            placeholder="Ej.: Deposité Bs 80 el 12/03 por caja del colegio, corresponde a febrero de María y José.">{{ old('nota') }}</textarea>
                        <p class="text-xs text-slate-500 mt-1">Sin archivos adjuntos: el aviso se compone solo de este texto.</p>
                        <x-input-error :messages="$errors->get('nota')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Registrar aviso</x-primary-button>
                        <a href="{{ route('aporte.avisos.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
