{{--
    Vista: Importar alumnos desde Excel (paso inicial).
    Permite cargar de forma masiva a los estudiantes a partir de una planilla Excel o CSV,
    en lugar de registrarlos uno por uno. El proceso tiene dos pasos: descargar la plantilla
    y luego subir el archivo para revisarlo en una previsualización antes de guardar nada.
    Recibe del controlador $gestion (la gestión actual) y $cursos (cursos disponibles para
    inscribir a los alumnos importados). La usa el personal administrativo.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Importar alumnos desde Excel</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Mensajes de resultado (por ejemplo, cuando la importación terminó o el archivo no era válido). --}}
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                {{--
                    Paso 1: descarga de la plantilla. Así el usuario llena los datos con las columnas
                    exactas que espera el sistema y se reducen los errores de formato.
                --}}
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

                {{--
                    Paso 2: carga del archivo. Todavía no se guarda nada en la base de datos; el
                    archivo se analiza y se muestra una previsualización para que el usuario confirme.
                --}}
                <div class="border-t border-slate-100 pt-4">
                    <h3 class="font-semibold text-slate-800">Paso 2 — Cargar archivo y previsualizar</h3>
                    <p class="text-sm text-slate-500 mt-1 mb-4">
                        Nada se importa hasta que revise la previsualización y confirme.
                        Los duplicados se detectan y se informan por fila; no se sobrescribe ningún registro existente.
                    </p>

                    {{-- enctype="multipart/form-data" es necesario para que el formulario pueda enviar archivos. --}}
                    <form method="POST" action="{{ route('importacion.previsualizar') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div class="grid sm:grid-cols-3 gap-3">
                            {{-- Selector del archivo; solo se aceptan formatos de hoja de cálculo. --}}
                            <div>
                                <x-input-label for="archivo" value="Archivo Excel o CSV" />
                                <input id="archivo" type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                                    class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-md file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                                <x-input-error :messages="$errors->get('archivo')" class="mt-2" />
                            </div>
                            {{-- La gestión está fija en la gestión actual: solo se importa al año escolar en curso. --}}
                            <div>
                                <x-input-label for="gestion_id" value="Gestión" />
                                <select id="gestion_id" name="gestion_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                                    <option value="{{ $gestion->id }}" selected>{{ $gestion->nombre }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('gestion_id')" class="mt-2" />
                            </div>
                            {{--
                                Curso opcional: si se elige uno, los alumnos importados quedan inscritos en él;
                                si se deja vacío, solo se crean los registros de los alumnos.
                            --}}
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

                        {{-- Aviso del límite de filas, pensado para que el proceso no sature un equipo con pocos recursos. --}}
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
