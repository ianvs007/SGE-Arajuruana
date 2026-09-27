@php
    $citacion = $citacion ?? null;
    $incidencias = $incidencias ?? collect();
    $usuarios = $usuarios ?? collect();
    $padresPorEstudiante = $estudiantes->mapWithKeys(function ($estudiante) {
        return [
            $estudiante->id => $estudiante->responsables->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'parentesco' => $p->pivot->parentesco ?? '',
            ])->values(),
        ];
    });
@endphp

<div>
    <x-input-label for="estudiante_id" value="Estudiante" />
    <select id="estudiante_id" name="estudiante_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
        <option value="">Seleccione</option>
        @foreach ($estudiantes as $estudiante)
            <option value="{{ $estudiante->id }}" @selected((string) old('estudiante_id', $citacion?->estudiante_id) === (string) $estudiante->id)>
                {{ $estudiante->nombreCompleto() }} ({{ $estudiante->codigo }})
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('estudiante_id')" class="mt-2" />
</div>

<div>
    <x-input-label for="padre_id" value="Responsable familiar convocado" />
    <select id="padre_id" name="padre_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required
        data-selected="{{ old('padre_id', $citacion?->padre_id) }}">
        <option value="">Seleccione primero un estudiante</option>
    </select>
    <x-input-error :messages="$errors->get('padre_id')" class="mt-2" />
    <p class="mt-1 text-xs text-slate-500">El responsable se ajusta a la fecha y hora asignadas; no hay reserva de citas (§12).</p>
</div>

<div class="grid md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="fecha" value="Fecha asignada" />
        <x-text-input id="fecha" type="date" name="fecha" class="block mt-1 w-full" :value="old('fecha', optional($citacion?->fecha)->format('Y-m-d') ?? now()->toDateString())" required />
        <x-input-error :messages="$errors->get('fecha')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="hora" value="Hora asignada" />
        <x-text-input id="hora" type="time" name="hora" class="block mt-1 w-full" :value="old('hora', $citacion?->hora ? \Illuminate\Support\Str::of($citacion->hora)->substr(0, 5) : now()->format('H:i'))" required />
        <x-input-error :messages="$errors->get('hora')" class="mt-2" />
    </div>
</div>

<div>
    <x-input-label for="motivo" value="Motivo" />
    <x-text-input id="motivo" name="motivo" class="block mt-1 w-full" :value="old('motivo', $citacion?->motivo)" required maxlength="150" />
    <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
</div>

<div>
    <x-input-label for="incidencia_id" value="Incidencia asociada (opcional)" />
    <select id="incidencia_id" name="incidencia_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
        <option value="">Sin incidencia asociada</option>
        @foreach ($incidencias as $incidenciaItem)
            <option value="{{ $incidenciaItem->id }}" @selected((string) old('incidencia_id', $citacion?->incidencia_id) === (string) $incidenciaItem->id)>
                {{ optional($incidenciaItem->fecha)->format('d/m/Y') }} — {{ $incidenciaItem->etiquetaPublica() }}
                {{ $incidenciaItem->confidencial ? '(confidencial)' : '' }} — {{ $incidenciaItem->estudiante?->nombreCompleto() }}
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('incidencia_id')" class="mt-2" />
    <p class="mt-1 text-xs text-slate-500">Si la incidencia es confidencial, el texto dirigido al familiar no reproduce su detalle (§11).</p>
</div>

<div>
    <x-input-label for="descripcion" value="Texto dirigido al familiar" />
    <textarea id="descripcion" name="descripcion" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('descripcion', $citacion?->descripcion) }}</textarea>
    <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
</div>

<div>
    <x-input-label for="estado" value="Estado" />
    <select id="estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
        @foreach ($estados as $value => $label)
            <option value="{{ $value }}" @selected(old('estado', $citacion?->estado ?? 'pendiente') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('estado')" class="mt-2" />
</div>

<div class="border-t border-slate-100 pt-4">
    <h4 class="font-semibold text-slate-700 text-sm mb-3">Acuerdos y seguimiento (§12)</h4>

    <div>
        <x-input-label for="acuerdos" value="Acuerdos alcanzados" />
        <textarea id="acuerdos" name="acuerdos" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" placeholder="Qué se acordó con el responsable…">{{ old('acuerdos', $citacion?->acuerdos) }}</textarea>
        <x-input-error :messages="$errors->get('acuerdos')" class="mt-2" />
    </div>

    <div class="grid md:grid-cols-2 gap-4 mt-4">
        <div>
            <x-input-label for="seguimiento_responsable_id" value="Responsable del seguimiento" />
            <select id="seguimiento_responsable_id" name="seguimiento_responsable_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
                <option value="">Sin asignar</option>
                @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario->id }}" @selected((string) old('seguimiento_responsable_id', $citacion?->seguimiento_responsable_id) === (string) $usuario->id)>{{ $usuario->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('seguimiento_responsable_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="fecha_revision" value="Fecha de revisión" />
            <x-text-input id="fecha_revision" type="date" name="fecha_revision" class="block mt-1 w-full" :value="old('fecha_revision', optional($citacion?->fecha_revision)->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('fecha_revision')" class="mt-2" />
        </div>
    </div>

    <div class="mt-4">
        <x-input-label for="observaciones_seguimiento" value="Observaciones de seguimiento" />
        <textarea id="observaciones_seguimiento" name="observaciones_seguimiento" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observaciones_seguimiento', $citacion?->observaciones_seguimiento) }}</textarea>
        <x-input-error :messages="$errors->get('observaciones_seguimiento')" class="mt-2" />
    </div>
</div>

<script>
    (function () {
        const padresPorEstudiante = @json($padresPorEstudiante);
        const estudianteSelect = document.getElementById('estudiante_id');
        const padreSelect = document.getElementById('padre_id');
        const incidenciaSelect = document.getElementById('incidencia_id');

        function actualizarPadres() {
            const estudianteId = estudianteSelect.value;
            const padres = padresPorEstudiante[estudianteId] || [];
            const selectedPadre = padreSelect.dataset.selected || '';
            padreSelect.innerHTML = '';

            if (!estudianteId) {
                padreSelect.innerHTML = '<option value="">Seleccione primero un estudiante</option>';
                return;
            }

            if (padres.length === 0) {
                padreSelect.innerHTML = '<option value="">Sin responsables vinculados</option>';
                return;
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Seleccione';
            padreSelect.appendChild(placeholder);

            padres.forEach(function (padre) {
                const option = document.createElement('option');
                option.value = padre.id;
                option.textContent = padre.parentesco ? padre.name + ' (' + padre.parentesco + ')' : padre.name;
                if (String(padre.id) === String(selectedPadre)) {
                    option.selected = true;
                }
                padreSelect.appendChild(option);
            });
        }

        estudianteSelect.addEventListener('change', function () {
            padreSelect.dataset.selected = '';
            actualizarPadres();
        });

        actualizarPadres();
    })();
</script>
