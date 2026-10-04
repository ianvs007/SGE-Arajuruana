{{--
    Vista: Nuevo curso
    Formulario para crear un curso dentro de una gestión. Los campos vienen de
    la vista parcial cursos/_form.

    Variables que recibe del controlador:
    - $gestion: gestión a la que pertenecerá el curso.
    - $niveles: niveles disponibles para el formulario.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Nuevo curso — {{ $gestion->nombre }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- La gestión viaja en un campo oculto para que el curso quede asociado a ella --}}
                <form method="POST" action="{{ route('cursos.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="gestion_id" value="{{ $gestion->id }}">
                    @include('cursos._form')
                    <div class="flex gap-3">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('cursos.index', ['gestion_id' => $gestion->id]) }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
