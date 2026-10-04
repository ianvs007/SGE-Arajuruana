{{--
    Vista: Nuevo aviso
    Pantalla para que la dirección o secretaría redacte un aviso institucional.
    Los campos están en la vista parcial avisos/_form, que se comparte con la
    pantalla de edición.

    Variables que recibe del controlador:
    - $cursos y $estudiantes: listas para elegir destinatarios específicos.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Nuevo aviso</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Formulario que guarda el aviso; incluye los campos comunes del formulario parcial --}}
                <form method="POST" action="{{ route('avisos.store') }}" class="space-y-4">
                    @csrf
                    @include('avisos._form', ['cursos' => $cursos, 'estudiantes' => $estudiantes])

                    {{--
                        Si se marca "Publicar ahora", al guardar se arma la lista definitiva de
                        destinatarios. Si no, el aviso queda como borrador para publicarlo después.
                    --}}
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="publicar_ahora" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm" checked>
                        <span class="text-sm text-slate-700">Publicar ahora (si no, se guarda como borrador)</span>
                    </label>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('avisos.index') }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
