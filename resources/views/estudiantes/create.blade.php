<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo estudiante</h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('estudiantes.store') }}" class="space-y-4">
                    @csrf
                    @include('estudiantes._form')
                    <div class="grid md:grid-cols-2 gap-4 border-t pt-4">
                        <div>
                            <x-input-label for="padre_id" value="Vincular padre/madre (opcional)" />
                            <select id="padre_id" name="padre_id" class="border-gray-300 rounded-md shadow-sm mt-1 w-full">
                                <option value="">—</option>
                                @foreach ($padres as $padre)
                                    <option value="{{ $padre->id }}" @selected(old('padre_id') == $padre->id)>{{ $padre->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="parentesco" value="Parentesco" />
                            <x-text-input id="parentesco" name="parentesco" class="block mt-1 w-full" :value="old('parentesco', 'padre/madre')" />
                        </div>
                    </div>
                    <x-primary-button>Guardar</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
