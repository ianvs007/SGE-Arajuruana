<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Nuevo cargo</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('cuentas.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="padre_id" value="Padre / responsable" />
                        <select id="padre_id" name="padre_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                            <option value="">Seleccione</option>
                            @foreach ($padres as $padre)
                                <option value="{{ $padre->id }}" @selected((string) old('padre_id') === (string) $padre->id)>{{ $padre->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('padre_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="estudiante_id" value="Estudiante (opcional)" />
                        <select id="estudiante_id" name="estudiante_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">—</option>
                            @foreach ($estudiantes as $estudiante)
                                <option value="{{ $estudiante->id }}" @selected((string) old('estudiante_id') === (string) $estudiante->id)>{{ $estudiante->nombreCompleto() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('estudiante_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="concepto" value="Concepto" />
                        <x-text-input id="concepto" name="concepto" class="block mt-1 w-full" :value="old('concepto')" required />
                        <x-input-error :messages="$errors->get('concepto')" class="mt-2" />
                    </div>
                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="monto" value="Monto (Bs.)" />
                            <x-text-input id="monto" type="number" step="0.01" min="0.01" name="monto" class="block mt-1 w-full" :value="old('monto')" required />
                            <x-input-error :messages="$errors->get('monto')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fecha_emision" value="Fecha de emisión" />
                            <x-text-input id="fecha_emision" type="date" name="fecha_emision" class="block mt-1 w-full" :value="old('fecha_emision', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('fecha_emision')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fecha_vencimiento" value="Fecha de vencimiento" />
                            <x-text-input id="fecha_vencimiento" type="date" name="fecha_vencimiento" class="block mt-1 w-full" :value="old('fecha_vencimiento')" />
                            <x-input-error :messages="$errors->get('fecha_vencimiento')" class="mt-2" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="observacion" value="Observación" />
                        <textarea id="observacion" name="observacion" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observacion') }}</textarea>
                        <x-input-error :messages="$errors->get('observacion')" class="mt-2" />
                    </div>
                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('cuentas.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
