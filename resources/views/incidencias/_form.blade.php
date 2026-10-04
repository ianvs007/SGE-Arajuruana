{{--
    Parcial: campos del formulario de incidencias.
    Se comparte entre las vistas de creación y edición. Si recibe $incidencia (modo edición)
    los campos se rellenan con sus datos; old() conserva lo escrito si la validación falla.
    Necesita del controlador $estudiantes, $categorias (solo las activas) y $estados.
--}}
{{-- Si no nos pasaron una incidencia, la dejamos en null para que el operador ?-> no falle. --}}
@php($incidencia = $incidencia ?? null)
{{-- Selección del estudiante involucrado; se muestra el nombre y el código para evitar confusiones entre homónimos. --}}
<div>
    <x-input-label for="estudiante_id" value="Estudiante" />
    <select id="estudiante_id" name="estudiante_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
        <option value="">Seleccione</option>
        @foreach ($estudiantes as $estudiante)
            <option value="{{ $estudiante->id }}" @selected((string) old('estudiante_id', $incidencia?->estudiante_id) === (string) $estudiante->id)>{{ $estudiante->nombreCompleto() }} ({{ $estudiante->codigo }})</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('estudiante_id')" class="mt-2" />
</div>

{{-- Fecha del hecho (por defecto la de hoy) y categoría de la incidencia. --}}
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="fecha" value="Fecha" />
        <x-text-input id="fecha" type="date" name="fecha" class="block mt-1 w-full" :value="old('fecha', optional($incidencia?->fecha)->format('Y-m-d') ?? now()->toDateString())" required />
        <x-input-error :messages="$errors->get('fecha')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="categoria_id" value="Categoría (configurable)" />
        <select id="categoria_id" name="categoria_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
            <option value="">Seleccione categoría…</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected((string) old('categoria_id', $incidencia?->categoria_id) === (string) $categoria->id)>{{ $categoria->nombre }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('categoria_id')" class="mt-2" />
        {{-- Si todavía no existen categorías activas, orientamos al usuario a la pantalla donde se crean. --}}
        @if ($categorias->isEmpty())
            <p class="mt-1 text-xs text-amber-700">No hay categorías activas. Créelas en <a class="underline" href="{{ route('incidencias.categorias') }}">Categorías</a>.</p>
        @endif
    </div>
</div>

{{-- Descripción de lo ocurrido (obligatoria). --}}
<div>
    <x-input-label for="descripcion" value="Descripción del hecho" />
    <textarea id="descripcion" name="descripcion" rows="4" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" required>{{ old('descripcion', $incidencia?->descripcion) }}</textarea>
    <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
</div>

{{-- Actuaciones o medidas que se tomaron con el estudiante. --}}
<div>
    <x-input-label for="medida_accion" value="Actuaciones / medida aplicada" />
    <textarea id="medida_accion" name="medida_accion" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('medida_accion', $incidencia?->medida_accion) }}</textarea>
    <x-input-error :messages="$errors->get('medida_accion')" class="mt-2" />
</div>

{{-- Observaciones de uso interno del personal. --}}
<div>
    <x-input-label for="observaciones" value="Observaciones internas" />
    <textarea id="observaciones" name="observaciones" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observaciones', $incidencia?->observaciones) }}</textarea>
    <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
</div>

{{-- Estado de seguimiento del caso y marca de confidencialidad. --}}
<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="estado_seguimiento" value="Estado de seguimiento" />
        <select id="estado_seguimiento" name="estado_seguimiento" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
            {{-- Un caso nuevo empieza en estado "abierta" si no se elige otro. --}}
            @foreach ($estados as $value => $label)
                <option value="{{ $value }}" @selected(old('estado_seguimiento', $incidencia?->estado_seguimiento ?? 'abierta') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('estado_seguimiento')" class="mt-2" />
    </div>
    {{--
        Casilla de caso confidencial. El campo oculto con valor 0 garantiza que se envíe "0"
        cuando la casilla está desmarcada. Un caso confidencial queda restringido a Administración.
    --}}
    <div class="flex items-end pb-1">
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="hidden" name="confidencial" value="0">
            <input type="checkbox" name="confidencial" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm"
                @checked(old('confidencial', $incidencia?->confidencial))>
            <span>
                <strong>Caso confidencial</strong>
                <span class="block text-xs text-slate-500">Solo Administración podrá verlo. No aparecerá en historial, reportes ni paneles de otros roles.</span>
            </span>
        </label>
        <x-input-error :messages="$errors->get('confidencial')" class="mt-2" />
    </div>
</div>
