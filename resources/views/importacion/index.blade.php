<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Importar alumnos desde Excel</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-800">Paso 1 — Plantilla</h3>
                        <p class="text-sm text-slate-500 mt-1">
                            Descargue la plantilla con campos básicos y ejemplos ficticios.
                            Complete una fila por alumno. Las columnas <strong>documento</strong> y
                            <strong>fecha_nacimiento</strong> están formateadas como texto para no perder ceros iniciales.
                        </p>
                    </div>
                    <a href="{{ route('importacion.plantilla') }}">
                        <x-secondary-button type="button">Descargar plantilla (.xlsx)</x-secondary-button>
                    </a>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <h3 class="font-semibold text-slate-800">Paso 2 — Cargar archivo y previsualizar</h3>
                    <p class="text-sm text-slate-500 mt-1 mb-4">
                        Nada se importa hasta que revise la previsualización y confirme.
                        Los duplicados se detectan y se informan por fila; no se sobrescribe ningún registro existente.
                    </p>

                    <form method="POST" action="{{ route('importacion.previsualizar') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div class="grid sm:grid-cols-3 gap-3">
                            <div>
                                <x-input-label for="archivo" value="Archivo Excel o CSV" />
                                <input id="archivo" type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                                    class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-md file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                                <x-input-error :messages="$errors->get('archivo')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="gestion_id" value="Gestión" />
                                <select id="gestion_id" name="gestion_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                                    <option value="{{ $gestion->id }}" selected>{{ $gestion->nombre }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('gestion_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="curso_id" value="Curso (contexto de inscripción)" />
                                <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                                    <option value="">Sin inscripción (solo crear alumnos)</option>
                                    @foreach ($cursos as $curso)
                                        <option value="{{ $curso->id }}" @selected((string) old('curso_id') === (string) $curso->id)>{{ $curso->etiqueta() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('curso_id')" class="mt-2" />
                            </div>
                        </div>

                        <div class="bg-sky-50 border border-sky-200 text-sky-800 px-4 py-3 rounded text-sm">
                            Límite operativo por archivo: <strong>500 filas</strong> (equipo de recursos modestos).
                            Para volúmenes mayores, divida en lotes.
                        </div>

                        <x-primary-button>Previsualizar importación</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
