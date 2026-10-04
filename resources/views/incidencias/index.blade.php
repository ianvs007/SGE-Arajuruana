{{--
    Vista: Listado de incidencias.
    Muestra los casos disciplinarios registrados, con filtros por alumno, estado y categoría.
    Recibe del controlador:
      - $incidencias: colección paginada de casos ya filtrados según el rol del usuario.
      - $estados: lista de estados de seguimiento (clave => etiqueta).
      - $categorias: categorías disponibles para el filtro.
      - $soloLectura (opcional): verdadero cuando el usuario solo puede consultar, por ejemplo
        un docente que ve los casos de los alumnos de sus cursos.
    La usan dirección/regencia (gestión completa) y los docentes (solo consulta).
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Incidencias</h2>
            {{-- Los botones de categorías y de nuevo registro solo los ve quien tiene el permiso para gestionar incidencias. --}}
            <div class="flex gap-2">
                @can('incidencias.gestionar')
                    <a href="{{ route('incidencias.categorias') }}"><x-secondary-button type="button">Categorías</x-secondary-button></a>
                    <a href="{{ route('incidencias.create') }}"><x-primary-button type="button">Nueva incidencia</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            {{-- Aviso que solo aparece en modo de solo lectura, para que el usuario sepa por qué no puede editar. --}}
            @if ($soloLectura ?? false)
                <div class="mb-4 text-sm text-slate-700 bg-sky-50 border border-sky-200 rounded p-3">
                    Consulta de casos disciplinarios de los alumnos de sus cursos asignados, en solo lectura.
                    Los casos confidenciales no se muestran (§11).
                </div>
            @endif
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{--
                    Filtros de búsqueda. Se envían por GET y request() recupera los valores elegidos,
                    así los filtros siguen marcados después de recargar la página.
                --}}
                <form method="GET" class="mb-4 grid sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <x-input-label for="q" value="Buscar alumno" />
                        <x-text-input id="q" name="q" class="block mt-1 w-full" :value="request('q')" placeholder="Nombre o código" />
                    </div>
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todos</option>
                            @foreach ($estados as $value => $label)
                                <option value="{{ $value }}" @selected((string) request('estado') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="categoria_id" value="Categoría" />
                        <select id="categoria_id" name="categoria_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                            <option value="">Todas</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}" @selected((string) request('categoria_id') === (string) $categoria->id)>{{ $categoria->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        {{-- El filtro de casos confidenciales solo se ofrece a los roles autorizados para verlos. --}}
                        @can('incidencias.confidenciales')
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="solo_confidenciales" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm" @checked(request()->boolean('solo_confidenciales'))>
                                Solo confidenciales
                            </label>
                        @endcan
                        <x-primary-button>Filtrar</x-primary-button>
                    </div>
                </form>

                {{-- Tabla de incidencias. --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Fecha</th>
                                <th class="pr-3">Estudiante</th>
                                <th class="pr-3">Categoría</th>
                                <th class="pr-3">Estado</th>
                                <th class="pr-3">Registrado por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Los casos confidenciales se resaltan con un fondo violeta y una etiqueta junto al nombre. --}}
                            @forelse ($incidencias as $incidencia)
                                <tr class="border-b border-slate-100 {{ $incidencia->confidencial ? 'bg-violet-50/60' : '' }}">
                                    <td class="py-2.5 pr-3">{{ optional($incidencia->fecha)->format('d/m/Y') }}</td>
                                    <td class="pr-3">
                                        {{ $incidencia->estudiante?->nombreCompleto() }}
                                        @if ($incidencia->confidencial)
                                            <span class="ml-1 text-[11px] bg-violet-100 text-violet-800 rounded px-1.5 py-0.5">Confidencial</span>
                                        @endif
                                    </td>
                                    {{-- Si el caso es antiguo y no tiene categoría, mostramos el campo "tipo" que se usaba antes. --}}
                                    <td class="pr-3">{{ $incidencia->categoria?->nombre ?? $incidencia->tipo }}</td>
                                    <td class="pr-3">
                                        {{-- Cada estado de seguimiento tiene su color: rojo abierta, ámbar en seguimiento y verde cerrada. --}}
                                        @php($colores = ['abierta' => 'bg-rose-100 text-rose-800', 'en_seguimiento' => 'bg-amber-100 text-amber-800', 'cerrada' => 'bg-emerald-100 text-emerald-800'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$incidencia->estado_seguimiento] ?? '' }}">{{ $incidencia->nombreEstado() }}</span>
                                    </td>
                                    <td class="pr-3">{{ $incidencia->registrador?->name ?? '—' }}</td>
                                    {{-- En modo de solo lectura se reemplaza el enlace de edición por un texto informativo. --}}
                                    <td class="text-right whitespace-nowrap">
                                        @if (! ($soloLectura ?? false))
                                            <a href="{{ route('incidencias.edit', $incidencia) }}" class="text-sky-700 hover:underline">Ver / Editar</a>
                                        @else
                                            <span class="text-xs text-slate-400">Solo consulta</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">No hay incidencias para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Paginación del listado. --}}
                <div class="mt-4">{{ $incidencias->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
