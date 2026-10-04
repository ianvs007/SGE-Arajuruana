{{--
    Vista: Previsualización de la importación de alumnos.
    Muestra el resultado del análisis del archivo Excel antes de guardar cualquier dato.
    Cada fila se clasifica como aceptada, con advertencia (posible duplicado) o rechazada
    (datos inválidos), y el usuario decide si confirma la importación.
    Recibe del controlador:
      - $datos: nombre del archivo y el arreglo 'filas' con los datos, la decisión, los errores
        y las advertencias de cada fila.
      - $gestion y $curso: gestión y curso de destino ($curso puede ser null).
      - $aceptadas, $advertidas, $rechazadas: colecciones con las filas de cada grupo.
    La usa el personal administrativo que está realizando la importación.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Previsualización de importación</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{-- Resumen: archivo, destino y cantidad de filas por cada estado. --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex flex-wrap gap-6 text-sm">
                    <div><span class="text-slate-500">Archivo:</span> <strong>{{ $datos['nombre_archivo'] }}</strong></div>
                    <div><span class="text-slate-500">Gestión:</span> <strong>{{ $gestion->nombre }}</strong></div>
                    <div><span class="text-slate-500">Curso destino:</span> <strong>{{ $curso?->etiqueta() ?? 'Sin inscripción' }}</strong></div>
                </div>
                <div class="flex flex-wrap gap-4 mt-4 text-sm">
                    <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800">Aceptadas: {{ $aceptadas->count() }}</span>
                    <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800">Con advertencia: {{ $advertidas->count() }}</span>
                    <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-800">Rechazadas: {{ $rechazadas->count() }}</span>
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Las filas rechazadas NO se importan. Las advertidas se importan solo si usted lo marca explícitamente.
                    La previsualización caduca a los 30 minutos.
                </p>
            </div>

            {{-- Tabla con el detalle de cada fila leída del archivo. --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-3">Detalle por fila</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-left">
                            <tr>
                                <th class="py-2 px-3">Fila</th>
                                <th class="px-3">Código</th>
                                <th class="px-3">Alumno</th>
                                <th class="px-3">Documento</th>
                                <th class="px-3">Nacimiento</th>
                                <th class="px-3">Sexo</th>
                                <th class="px-3">Estado</th>
                                <th class="px-3">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{--
                                Recorremos las filas del archivo. El fondo de cada fila cambia de color según la
                                decisión (rojo si fue rechazada, ámbar si tiene advertencia) para identificarlas de un vistazo.
                            --}}
                            @foreach ($datos['filas'] as $fila)
                                <tr class="border-t border-slate-100 align-top
                                    {{ $fila['decision'] === 'rechazada' ? 'bg-rose-50/60' : ($fila['decision'] === 'advertencia' ? 'bg-amber-50/60' : '') }}">
                                    <td class="py-2 px-3">{{ $fila['fila'] }}</td>
                                    <td class="px-3">{{ $fila['datos']['codigo'] }}</td>
                                    <td class="px-3">{{ trim($fila['datos']['apellidos'].' '.$fila['datos']['nombres']) }}</td>
                                    <td class="px-3">{{ $fila['datos']['documento'] ?: '—' }}</td>
                                    <td class="px-3">{{ $fila['datos']['fecha_nacimiento'] ?? '—' }}</td>
                                    <td class="px-3">{{ $fila['datos']['sexo'] ?: '—' }}</td>
                                    {{-- Etiqueta con el estado de la fila. --}}
                                    <td class="px-3">
                                        @if ($fila['decision'] === 'aceptada')
                                            <span class="text-xs bg-emerald-100 text-emerald-800 rounded px-2 py-0.5">Aceptada</span>
                                        @elseif ($fila['decision'] === 'advertencia')
                                            <span class="text-xs bg-amber-100 text-amber-800 rounded px-2 py-0.5">Advertencia</span>
                                        @else
                                            <span class="text-xs bg-rose-100 text-rose-800 rounded px-2 py-0.5">Rechazada</span>
                                        @endif
                                    </td>
                                    {{-- Motivos: primero los errores que impiden importar y luego las advertencias. --}}
                                    <td class="px-3 text-xs text-slate-600">
                                        @foreach ($fila['errores'] as $error)
                                            <div>• {{ $error }}</div>
                                        @endforeach
                                        @foreach ($fila['advertencias'] as $advertencia)
                                            <div>• {{ $advertencia }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Confirmación final de la importación. --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('importacion.confirmar') }}" class="space-y-4"
                    onsubmit="return confirm('¿Confirmar la importación? Las filas rechazadas quedarán fuera y no se modificará ningún registro existente.');">
                    @csrf
                    {{--
                        La casilla para incluir las filas con advertencia solo aparece si existen. Por defecto
                        no se importan, para que el usuario las revise antes y no se creen alumnos duplicados.
                    --}}
                    @if ($advertidas->isNotEmpty())
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="importar_advertidas" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                            <span>
                                Importar también las <strong>{{ $advertidas->count() }}</strong> fila(s) con advertencia
                                (posibles duplicados sin documento). Revíselas arriba antes de marcar esta opción:
                                no se fusiona ningún registro existente.
                            </span>
                        </label>
                    @endif
                    {{-- El botón queda deshabilitado si no hay ninguna fila que se pueda importar. --}}
                    <div class="flex flex-wrap gap-3">
                        <x-primary-button :disabled="$aceptadas->count() + $advertidas->count() === 0">
                            Confirmar importación ({{ $aceptadas->count() }} aceptadas)
                        </x-primary-button>
                    </div>
                </form>
                {{-- Formulario aparte para descartar la previsualización guardada temporalmente y volver al inicio. --}}
                <form method="POST" action="{{ route('importacion.cancelar') }}" class="mt-3">
                    @csrf @method('DELETE')
                    <button class="text-sm text-slate-500 hover:underline">Descartar previsualización y volver</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
