{{--
    Vista: Nueva inscripción.
    Permite inscribir a un alumno en un curso dentro de una gestión (año escolar). La
    inscripción es lo que vincula al estudiante con su curso en un año concreto, y gracias a
    ella se conserva el historial de todos los años.
    Recibe del controlador:
      - $gestion: la gestión en la que se inscribe.
      - $estudiantes: alumnos activos que todavía no están inscritos en esa gestión.
      - $cursos: cursos disponibles de la gestión.
    La usa el personal administrativo con permiso para gestionar inscripciones.
--}}
<x-app-layout>
    {{-- El título incluye el nombre de la gestión para que quede claro en qué año se inscribe. --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Nueva inscripción — {{ $gestion->nombre }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('inscripciones.store') }}" class="space-y-4">
                    @csrf
                    {{-- La gestión viaja oculta porque ya fue elegida en el listado de inscripciones. --}}
                    <input type="hidden" name="gestion_id" value="{{ $gestion->id }}">

                    {{-- Selección del alumno. Solo aparecen los que aún no tienen inscripción en esta gestión. --}}
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
                        {{-- Aviso cuando la lista queda vacía, para que el usuario entienda el motivo. --}}
                        @if ($estudiantes->isEmpty())
                            <p class="mt-2 text-sm text-amber-700">Todos los alumnos activos ya están inscritos en esta gestión, o no hay alumnos registrados.</p>
                        @endif
                    </div>

                    {{-- Curso al que se inscribe y fecha de inscripción (por defecto la fecha de hoy). --}}
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="curso_id" value="Curso" />
                            <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                                <option value="">Seleccione curso…</option>
                                @foreach ($cursos as $curso)
                                    <option value="{{ $curso->id }}" @selected((string) old('curso_id') === (string) $curso->id)>{{ $curso->etiqueta() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('curso_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fecha_inscripcion" value="Fecha de inscripción" />
                            <x-text-input id="fecha_inscripcion" type="date" name="fecha_inscripcion" class="block mt-1 w-full" :value="old('fecha_inscripcion', now()->toDateString())" />
                            <x-input-error :messages="$errors->get('fecha_inscripcion')" class="mt-2" />
                        </div>
                    </div>

                    {{-- Toda inscripción nueva nace con estado "activa"; los demás estados se asignan después al editarla. --}}
                    <input type="hidden" name="estado" value="activa">

                    <div>
                        <x-input-label for="observaciones" value="Observaciones" />
                        <textarea id="observaciones" name="observaciones" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observaciones') }}</textarea>
                        <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
                    </div>

                    {{-- Botones: inscribir o volver al listado manteniendo la misma gestión seleccionada. --}}
                    <div class="flex gap-3">
                        <x-primary-button>Inscribir</x-primary-button>
                        <a href="{{ route('inscripciones.index', ['gestion_id' => $gestion->id]) }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
