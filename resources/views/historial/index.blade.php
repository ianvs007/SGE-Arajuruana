<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Historial unificado de estudiantes</h2>
    </x-slot>
    <div class="py-8"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-6">
            <form method="GET" class="mb-4 flex flex-col sm:flex-row gap-2">
                <x-text-input name="q" :value="$q" class="block w-full" placeholder="Buscar estudiante" />
                <x-primary-button>Buscar</x-primary-button>
            </form>
            <div class="overflow-x-auto -mx-6 px-6 sm:mx-0 sm:px-0">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left border-b"><th class="py-2">Código</th><th>Estudiante</th><th>Curso</th><th></th></tr></thead>
                <tbody>
                    @forelse ($estudiantes as $estudiante)
                        <tr class="border-b">
                            <td class="py-2">{{ $estudiante->codigo }}</td>
                            <td>{{ $estudiante->nombreCompleto() }}</td>
                            <td>{{ $estudiante->curso?->etiqueta() }}</td>
                            <td class="text-right"><a href="{{ route('historial.show', $estudiante) }}" class="text-indigo-600">Ver línea de tiempo</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">No hay estudiantes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div class="mt-4">{{ $estudiantes->links() }}</div>
        </div>
    </div></div>
</x-app-layout>
