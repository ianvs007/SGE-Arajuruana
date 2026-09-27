<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Historial — {{ $estudiante->nombreCompleto() }}</h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <ul class="space-y-3 text-sm">
                    @forelse ($eventos as $evento)
                        <li class="border-l-4 border-indigo-300 pl-3">
                            <div class="text-xs text-gray-500">{{ optional($evento['fecha'])->format('d/m/Y') }} · {{ $evento['tipo'] }}</div>
                            <div>{{ $evento['detalle'] }}</div>
                        </li>
                    @empty
                        <li class="text-gray-500">Sin eventos registrados.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
