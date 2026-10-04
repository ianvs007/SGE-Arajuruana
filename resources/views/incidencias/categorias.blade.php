{{--
    Vista: Categorías de incidencias.
    Permite configurar los tipos de faltas o incidencias disciplinarias que se usan al registrar
    un caso (por ejemplo, "Atraso reiterado" o "Falta de respeto"). Las categorías no vienen
    fijas en el código: cada institución las define según su reglamento interno.
    Recibe del controlador $categorias con todas las categorías registradas.
    La usa el personal con permiso para gestionar incidencias.
--}}
<x-app-layout>
    {{-- Encabezado con botón para volver al listado de incidencias. --}}
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Categorías de incidencias</h2>
            <a href="{{ route('incidencias.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{-- Aviso: las categorías salen del reglamento de la institución y desactivarlas no borra los casos ya registrados. --}}
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm">
                Las categorías se configuran según el reglamento interno de convivencia de la institución.
                No se inventan infracciones ni artículos en el sistema (§11). Desactivar no borra: las
                incidencias existentes conservan su categoría.
            </div>

            {{-- Formulario para crear una nueva categoría. --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-3">Nueva categoría</h3>
                <form method="POST" action="{{ route('incidencias.categorias.store') }}" class="grid sm:grid-cols-3 gap-3 items-end">
                    @csrf
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input id="nombre" name="nombre" class="block mt-1 w-full" :value="old('nombre')" required maxlength="100" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="descripcion" value="Descripción (opcional)" />
                        <x-text-input id="descripcion" name="descripcion" class="block mt-1 w-full" :value="old('descripcion')" />
                    </div>
                    <div class="sm:col-span-3">
                        <x-primary-button>Crear categoría</x-primary-button>
                    </div>
                </form>
            </div>

            {{--
                Categorías existentes. Cada una se muestra como su propio formulario de edición,
                de modo que se puede cambiar el nombre, la descripción o activarla/desactivarla
                directamente desde la lista, sin abrir otra pantalla.
            --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-3">Categorías existentes</h3>
                <div class="space-y-3">
                    @forelse ($categorias as $categoria)
                        <form method="POST" action="{{ route('incidencias.categorias.update', $categoria) }}" class="grid sm:grid-cols-12 gap-3 items-end border border-slate-100 rounded p-3">
                            @csrf @method('PUT')
                            <div class="sm:col-span-4">
                                <x-input-label value="Nombre" />
                                <x-text-input name="nombre" class="block mt-1 w-full" :value="$categoria->nombre" required maxlength="100" />
                            </div>
                            <div class="sm:col-span-5">
                                <x-input-label value="Descripción" />
                                <x-text-input name="descripcion" class="block mt-1 w-full" :value="$categoria->descripcion" />
                            </div>
                            {{--
                                El campo oculto con valor 0 va antes de la casilla: si la casilla queda
                                desmarcada el navegador no la envía, y así el servidor recibe igualmente "0".
                            --}}
                            <div class="sm:col-span-1 flex items-center pb-1">
                                <label class="flex items-center gap-1 text-xs text-slate-600">
                                    <input type="hidden" name="activa" value="0">
                                    <input type="checkbox" name="activa" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm" @checked($categoria->activa)>
                                    Activa
                                </label>
                            </div>
                            <div class="sm:col-span-2 flex gap-2 justify-end">
                                <x-primary-button>Guardar</x-primary-button>
                            </div>
                        </form>
                    @empty
                        <p class="text-sm text-slate-500">No hay categorías creadas todavía.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
