{{--
    Vista: Listado de gestiones académicas.
    Muestra todas las gestiones (años escolares) registradas en el sistema, con su período,
    la cantidad de cursos asociados y su estado (actual, abierta o histórica).
    Recibe del controlador $gestiones, una colección paginada que ya incluye el conteo de
    cursos (cursos_count). Desde aquí el administrador crea, edita, elimina o marca como
    actual una gestión.
--}}
<x-app-layout>
    {{-- Encabezado con el título y el botón para registrar una nueva gestión. --}}
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Gestiones académicas</h2>
            <a href="{{ route('gestiones.create') }}"><x-primary-button type="button">Nueva gestión</x-primary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes de confirmación o error de la última acción. --}}
            @include('partials.flash')

            {{-- Tabla con el listado de gestiones. --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-left">
                            <tr>
                                <th class="py-3 px-4">Gestión</th>
                                <th class="px-4">Período</th>
                                <th class="px-4">Cursos</th>
                                <th class="px-4">Estado</th>
                                <th class="px-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{--
                                Recorremos cada gestión con @forelse; si la colección está vacía se
                                ejecuta el bloque @empty y se muestra un mensaje en lugar de la tabla vacía.
                            --}}
                            @forelse ($gestiones as $gestion)
                                <tr class="border-t border-slate-100">
                                    <td class="py-3 px-4 font-medium text-slate-800">{{ $gestion->nombre }}</td>
                                    <td class="px-4 text-slate-600">
                                        {{-- El período solo se muestra si la gestión tiene ambas fechas cargadas. --}}
                                        @if ($gestion->fecha_inicio && $gestion->fecha_fin)
                                            {{ $gestion->fecha_inicio->format('d/m/Y') }} — {{ $gestion->fecha_fin->format('d/m/Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4">{{ $gestion->cursos_count }}</td>
                                    <td class="px-4">
                                        {{--
                                            Etiqueta de estado: "Actual" es la gestión con la que trabaja todo el
                                            sistema, "Abierta" sigue vigente y "Histórica" ya fue cerrada.
                                        --}}
                                        @if ($gestion->es_actual)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800">Actual</span>
                                        @elseif ($gestion->activa)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-sky-100 text-sky-800">Abierta</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-600">Histórica</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                        {{--
                                            El botón "Marcar actual" solo aparece en las gestiones que todavía no
                                            lo son. Se pide confirmación porque cambia la gestión de trabajo de todo el sistema.
                                        --}}
                                        @unless ($gestion->es_actual)
                                            <form method="POST" action="{{ route('gestiones.marcar-actual', $gestion) }}" class="inline"
                                                onsubmit="return confirm('¿Marcar {{ $gestion->nombre }} como gestión actual?')">
                                                @csrf
                                                <button class="text-sky-700 hover:underline">Marcar actual</button>
                                            </form>
                                        @endunless
                                        {{-- Enlace de edición y formulario de eliminación (DELETE) con confirmación previa. --}}
                                        <a class="text-sky-700 hover:underline" href="{{ route('gestiones.edit', $gestion) }}">Editar</a>
                                        <form method="POST" action="{{ route('gestiones.destroy', $gestion) }}" class="inline"
                                            onsubmit="return confirm('¿Eliminar la gestión {{ $gestion->nombre }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-rose-600 hover:underline">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-500">
                                        No hay gestiones registradas. Cree la primera gestión académica.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Enlaces de paginación generados por Laravel. --}}
                <div class="px-4 py-3 border-t border-slate-100">{{ $gestiones->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
