{{--
    Vista: Editar estudiante
    Permite actualizar los datos de un estudiante reutilizando la vista parcial
    estudiantes/_form con la información actual.

    Variables que recibe del controlador:
    - $estudiante: el estudiante a editar.
    - $cursos: cursos disponibles para asignar.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar estudiante</h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Formulario de actualización; se envía con PUT porque modifica un registro existente --}}
                <form method="POST" action="{{ route('estudiantes.update', $estudiante) }}" class="space-y-4">
                    @csrf @method('PUT')
                    @include('estudiantes._form', ['estudiante' => $estudiante])
                    <x-primary-button>Actualizar</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
