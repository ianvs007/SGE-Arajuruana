{{--
    Vista: Autorizar salida.
    Pantalla donde se autoriza que un alumno salga de la unidad educativa durante el horario
    de clases (por salud, emergencia, motivo familiar, etc.). Autorizar es solo el primer paso:
    la salida real y el retorno se registran después desde el detalle de la salida.
    Recibe del controlador $estudiantes (alumnos para elegir) y $motivos (lista valor => etiqueta).
    La usa el personal con el permiso salidas.autorizar.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Autorizar salida</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Aviso para que el usuario no confunda la autorización con la salida efectiva del alumno. --}}
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm mb-4">
                    Autorizar <strong>no significa que el alumno ya se retiró</strong>. La persona que retira y la hora
                    efectiva se registran después, con verificación manual del documento por Administración.
                </div>

                <form method="POST" action="{{ route('salidas.store') }}" class="space-y-4">
                    @csrf

                    {{-- Selección del alumno, mostrando nombre y código. --}}
                    <div>
                        <x-input-label for="estudiante_id" value="Alumno" />
                        <select id="estudiante_id" name="estudiante_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                            <option value="">Seleccione alumno…</option>
                            @foreach ($estudiantes as $estudiante)
                                <option value="{{ $estudiante->id }}" @selected((string) old('estudiante_id') === (string) $estudiante->id)>
                                    {{ $estudiante->nombreCompleto() }} ({{ $estudiante->codigo }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('estudiante_id')" class="mt-2" />
                    </div>

                    {{-- Fecha de la salida (por defecto hoy) y motivo elegido de la lista que envía el controlador. --}}
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="fecha" value="Fecha" />
                            <x-text-input id="fecha" type="date" name="fecha" class="block mt-1 w-full" :value="old('fecha', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('fecha')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="motivo" value="Motivo" />
                            <select id="motivo" name="motivo" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                                <option value="">Seleccione motivo…</option>
                                @foreach ($motivos as $value => $label)
                                    <option value="{{ $value }}" @selected(old('motivo') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="observacion" value="Observación (opcional)" />
                        <textarea id="observacion" name="observacion" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observacion') }}</textarea>
                        <x-input-error :messages="$errors->get('observacion')" class="mt-2" />
                    </div>

                    {{-- Botones: autorizar o volver al listado de salidas. --}}
                    <div class="flex gap-3">
                        <x-primary-button>Autorizar salida</x-primary-button>
                        <a href="{{ route('salidas.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
