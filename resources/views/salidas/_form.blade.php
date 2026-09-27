@php($salida = $salida ?? null)
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="estudiante_id" value="Estudiante" />
        <select id="estudiante_id" name="estudiante_id" class="border-gray-300 rounded-md shadow-sm mt-1 w-full" required>
            @foreach ($estudiantes as $est)
                <option value="{{ $est->id }}" @selected(old('estudiante_id', $salida?->estudiante_id) == $est->id)>{{ $est->nombreCompleto() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="fecha" value="Fecha" />
        <x-text-input id="fecha" type="date" name="fecha" class="block mt-1 w-full" :value="old('fecha', optional($salida?->fecha)->format('Y-m-d') ?? now()->toDateString())" required />
    </div>
    <div>
        <x-input-label for="hora_salida" value="Hora de salida" />
        <x-text-input id="hora_salida" type="time" name="hora_salida" class="block mt-1 w-full" :value="old('hora_salida', $salida?->hora_salida ? substr($salida->hora_salida,0,5) : now()->format('H:i'))" required />
    </div>
    <div>
        <x-input-label for="motivo" value="Motivo" />
        <select id="motivo" name="motivo" class="border-gray-300 rounded-md shadow-sm mt-1 w-full" required>
            @foreach (['salud','emergencia','familiar','otro'] as $m)
                <option value="{{ $m }}" @selected(old('motivo', $salida?->motivo) === $m)>{{ ucfirst($m) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="responsable_retiro" value="Responsable del retiro" />
        <x-text-input id="responsable_retiro" name="responsable_retiro" class="block mt-1 w-full" :value="old('responsable_retiro', $salida?->responsable_retiro)" required />
    </div>
    <div>
        <x-input-label for="documento_responsable" value="Documento responsable" />
        <x-text-input id="documento_responsable" name="documento_responsable" class="block mt-1 w-full" :value="old('documento_responsable', $salida?->documento_responsable)" />
    </div>
</div>
<div class="mt-4">
    <x-input-label for="observacion" value="Observación" />
    <textarea id="observacion" name="observacion" rows="3" class="border-gray-300 rounded-md shadow-sm mt-1 w-full">{{ old('observacion', $salida?->observacion) }}</textarea>
</div>
