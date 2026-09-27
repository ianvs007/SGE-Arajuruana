<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Cursos</h2>
            <a href="{{ route('cursos.create', ['gestion_id' => $gestion->id]) }}"><x-primary-button type="button">Nuevo curso</x-primary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
                    <div class="min-w-52">
                        <x-input-label for="gestion_id" value="Gestión" />
                        <select id="gestion_id" name="gestion_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" onchange="this.form.submit()">
                            @foreach ($gestiones as $g)
                                <option value="{{ $g->id }}" @selected($g->id === $gestion->id)>{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-secondary-button type="submit">Cambiar</x-secondary-button>
                    @if ($gestion->es_actual)
                        <span class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-1">Gestión actual</span>
                    @endif
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-left">
                            <tr>
                                <th class="py-3 px-4">Curso</th>
                                <th class="px-4">Nivel</th>
                                <th class="px-4">Paralelo</th>
                                <th class="px-4">Turno</th>
                                <th class="px-4">Inscritos</th>
                                <th class="px-4">Estado</th>
                                <th class="px-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cursos as $curso)
                                <tr class="border-t border-slate-100">
                                    <td class="py-3 px-4 font-medium text-slate-800">{{ $curso->nombre }}</td>
                                    <td class="px-4">{{ $curso->nivel ?? '—' }}</td>
                                    <td class="px-4">{{ $curso->paralelo ?? '—' }}</td>
                                    <td class="px-4">{{ $curso->nombreTurno() }}</td>
                                    <td class="px-4">{{ $curso->inscritos_count }}</td>
                                    <td class="px-4">
                                        @if ($curso->activo)
                                            <span class="text-xs bg-emerald-100 text-emerald-800 rounded px-2 py-0.5">Activo</span>
                                        @else
                                            <span class="text-xs bg-slate-100 text-slate-600 rounded px-2 py-0.5">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a class="text-sky-700 hover:underline" href="{{ route('cursos.edit', $curso) }}">Configurar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-500">
                                        No hay cursos en esta gestión. Cree el primero con «Nuevo curso».
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $cursos->withQueryString()->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
