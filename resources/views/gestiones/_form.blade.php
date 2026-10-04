{{--
    Parcial: campos del formulario de gestión académica.
    Lo incluyen tanto la vista de creación como la de edición, así evitamos repetir el mismo
    formulario dos veces. Si existe la variable $gestion (modo edición) los campos se rellenan
    con sus datos; si no, quedan vacíos. old() recupera lo que el usuario escribió cuando la
    validación falla, para que no tenga que volver a llenar todo.
--}}
{{-- Si la vista que nos incluye no definió $gestion, la dejamos en null para evitar errores. --}}
@php($gestion = $gestion ?? null)
{{-- Fila 1: nombre descriptivo de la gestión y año al que corresponde. --}}
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="nombre" value="Nombre de la gestión" />
        <x-text-input id="nombre" name="nombre" class="block mt-1 w-full" :value="old('nombre', $gestion?->nombre)" placeholder="Ej.: Gestión 2026" required />
        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="anio" value="Año" />
        <x-text-input id="anio" type="number" name="anio" class="block mt-1 w-full" :value="old('anio', $gestion?->anio)" min="2000" max="2100" required />
        <x-input-error :messages="$errors->get('anio')" class="mt-2" />
    </div>
</div>
{{-- Fila 2: fechas de inicio y fin del año escolar (opcionales). Se formatean como Y-m-d porque así lo exige el input de tipo date. --}}
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="fecha_inicio" value="Fecha de inicio" />
        <x-text-input id="fecha_inicio" type="date" name="fecha_inicio" class="block mt-1 w-full" :value="old('fecha_inicio', optional($gestion?->fecha_inicio)->format('Y-m-d'))" />
        <x-input-error :messages="$errors->get('fecha_inicio')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="fecha_fin" value="Fecha de fin" />
        <x-text-input id="fecha_fin" type="date" name="fecha_fin" class="block mt-1 w-full" :value="old('fecha_fin', optional($gestion?->fecha_fin)->format('Y-m-d'))" />
        <x-input-error :messages="$errors->get('fecha_fin')" class="mt-2" />
    </div>
</div>
{{-- Casilla para indicar si la gestión sigue abierta. En una gestión nueva viene marcada por defecto. --}}
<div class="flex flex-wrap gap-6">
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="activa" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm"
            @checked(old('activa', $gestion?->activa ?? true))>
        Gestión abierta (no histórica)
    </label>
</div>
{{-- Observaciones libres sobre la gestión. --}}
<div>
    <x-input-label for="observaciones" value="Observaciones" />
    <textarea id="observaciones" name="observaciones" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observaciones', $gestion?->observaciones) }}</textarea>
    <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
</div>
