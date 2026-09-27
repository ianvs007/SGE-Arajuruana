<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            Editar inscripción — {{ $inscripcion->estudiante?->nombreCompleto() }} ({{ $inscripcion->gestion->nombre }})
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('inscripciones.update', $inscripcion) }}" class="space-y-4">
                    @csrf @method('PUT')

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="curso_id" value="Curso" />
                            <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                                @foreach ($cursos as $curso)
                                    <option value="{{ $curso->id }}" @selected((int) old('curso_id', $inscripcion->curso_id) === $curso->id)>{{ $curso->etiqueta() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('curso_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="estado" value="Estado" />
                            <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                                @foreach (['activa' => 'Activa', 'retirada' => 'Retirada', 'trasladada' => 'Trasladada', 'cancelada' => 'Cancelada'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('estado', $inscripcion->estado) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('estado')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="fecha_inscripcion" value="Fecha de inscripción" />
                        <x-text-input id="fecha_inscripcion" type="date" name="fecha_inscripcion" class="block mt-1 w-full" :value="old('fecha_inscripcion', optional($inscripcion->fecha_inscripcion)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('fecha_inscripcion')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="observaciones" value="Observaciones" />
                        <textarea id="observaciones" name="observaciones" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observaciones', $inscripcion->observaciones) }}</textarea>
                        <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
                    </div>

                    <div class="flex gap-3">
                        <x-primary-button>Guardar cambios</x-primary-button>
                        <a href="{{ route('inscripciones.index', ['gestion_id' => $inscripcion->gestion_id]) }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
