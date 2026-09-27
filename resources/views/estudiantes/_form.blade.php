@php($estudiante = $estudiante ?? null)
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="codigo" value="Código" />
        <x-text-input id="codigo" name="codigo" class="block mt-1 w-full" :value="old('codigo', $estudiante?->codigo)" required />
        <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="documento" value="Documento" />
        <x-text-input id="documento" name="documento" class="block mt-1 w-full" :value="old('documento', $estudiante?->documento)" />
    </div>
    <div>
        <x-input-label for="nombres" value="Nombres" />
        <x-text-input id="nombres" name="nombres" class="block mt-1 w-full" :value="old('nombres', $estudiante?->nombres)" required />
    </div>
    <div>
        <x-input-label for="apellidos" value="Apellidos" />
        <x-text-input id="apellidos" name="apellidos" class="block mt-1 w-full" :value="old('apellidos', $estudiante?->apellidos)" required />
    </div>
    <div>
        <x-input-label for="fecha_nacimiento" value="Fecha de nacimiento" />
        <x-text-input id="fecha_nacimiento" type="date" name="fecha_nacimiento" class="block mt-1 w-full" :value="old('fecha_nacimiento', optional($estudiante?->fecha_nacimiento)->format('Y-m-d'))" />
    </div>
    <div>
        <x-input-label for="sexo" value="Sexo" />
        <select id="sexo" name="sexo" class="border-gray-300 rounded-md shadow-sm mt-1 w-full">
            <option value="">—</option>
            @foreach (['F' => 'Femenino', 'M' => 'Masculino', 'Otro' => 'Otro'] as $val => $label)
                <option value="{{ $val }}" @selected(old('sexo', $estudiante?->sexo) === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="curso_id" value="Curso" />
        <select id="curso_id" name="curso_id" class="border-gray-300 rounded-md shadow-sm mt-1 w-full">
            <option value="">Sin curso</option>
            @foreach ($cursos as $curso)
                <option value="{{ $curso->id }}" @selected(old('curso_id', $estudiante?->curso_id) == $curso->id)>{{ $curso->etiqueta() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="estado" value="Estado" />
        <select id="estado" name="estado" class="border-gray-300 rounded-md shadow-sm mt-1 w-full" required>
            @foreach (['activo', 'inactivo', 'retirado'] as $est)
                <option value="{{ $est }}" @selected(old('estado', $estudiante?->estado ?? 'activo') === $est)>{{ ucfirst($est) }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="mt-4">
    <x-input-label for="observaciones" value="Observaciones" />
    <textarea id="observaciones" name="observaciones" class="border-gray-300 rounded-md shadow-sm mt-1 w-full" rows="3">{{ old('observaciones', $estudiante?->observaciones) }}</textarea>
</div>
