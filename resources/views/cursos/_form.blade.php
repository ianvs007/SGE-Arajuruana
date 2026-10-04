{{--
    Vista parcial: Formulario de curso
    Campos comunes de un curso (nombre, nivel, grado, paralelo, turno, orden y
    si está activo). Se incluye en cursos/create y en cursos/edit.

    Variables que recibe: $curso (opcional, solo al editar) y $niveles (lista de niveles).
--}}
{{-- Al crear no existe el curso, así que lo dejamos en null para evitar errores --}}
@php($curso = $curso ?? null)
{{-- Nombre y nivel del curso --}}
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="nombre" value="Nombre del curso" />
        <x-text-input id="nombre" name="nombre" class="block mt-1 w-full" :value="old('nombre', $curso?->nombre)" placeholder="Ej.: 1ro de Primaria" required />
        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="nivel" value="Nivel" />
        <select id="nivel" name="nivel" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
            <option value="">—</option>
            @foreach ($niveles as $value => $label)
                <option value="{{ $value }}" @selected(old('nivel', $curso?->nivel) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('nivel')" class="mt-2" />
    </div>
</div>
{{-- Grado, paralelo, turno (por defecto mañana) y el orden en que aparece en las listas --}}
<div class="grid md:grid-cols-4 gap-4">
    <div>
        <x-input-label for="grado" value="Grado" />
        <x-text-input id="grado" name="grado" class="block mt-1 w-full" :value="old('grado', $curso?->grado)" placeholder="Ej.: 3ro" />
        <x-input-error :messages="$errors->get('grado')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="paralelo" value="Paralelo" />
        <x-text-input id="paralelo" name="paralelo" class="block mt-1 w-full" :value="old('paralelo', $curso?->paralelo)" placeholder="Ej.: A" maxlength="10" />
        <x-input-error :messages="$errors->get('paralelo')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="turno" value="Turno" />
        <select id="turno" name="turno" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
            @foreach (\App\Models\Curso::TURNOS as $value => $label)
                <option value="{{ $value }}" @selected(old('turno', $curso?->turno ?? 'manana') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('turno')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="orden" value="Orden de lista" />
        <x-text-input id="orden" type="number" name="orden" class="block mt-1 w-full" :value="old('orden', $curso?->orden)" min="0" />
        <x-input-error :messages="$errors->get('orden')" class="mt-2" />
    </div>
</div>
{{-- Casilla para activar o desactivar el curso; un curso nuevo queda activo por defecto --}}
<div>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm"
            @checked(old('activo', $curso?->activo ?? true))>
        Curso activo
    </label>
</div>
