<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Usuarios</h2>
            <a href="{{ route('users.create') }}"><x-primary-button type="button">Nuevo usuario</x-primary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="GET" class="mb-4 flex flex-col sm:flex-row gap-2">
                    <x-text-input name="q" value="{{ $q }}" class="block w-full" placeholder="Buscar por nombre, correo o documento" />
                    <x-primary-button>Buscar</x-primary-button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 text-slate-500">
                                <th class="py-2 pr-3">Nombre</th>
                                <th class="pr-3">Correo</th>
                                <th class="pr-3">Documento</th>
                                <th class="pr-3">Rol</th>
                                <th class="pr-3">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2.5 pr-3">{{ $user->name }}</td>
                                    <td class="pr-3">{{ $user->email }}</td>
                                    <td class="pr-3">{{ $user->documento ?? '—' }}</td>
                                    <td class="pr-3">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                                    <td class="pr-3">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $user->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $user->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="text-right whitespace-nowrap space-x-3">
                                        @if (auth()->user()->puedeGestionar($user))
                                            <a href="{{ route('users.edit', $user) }}" class="text-sky-700 hover:underline">Editar</a>
                                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar o inactivar este usuario?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                            </form>
                                        @else
                                            <span class="text-slate-400 text-xs">Sin autorización</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">No se encontraron usuarios.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
