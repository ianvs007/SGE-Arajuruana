{{--
    Vista parcial: Formulario de aviso
    Contiene los campos comunes para crear o editar un aviso, por eso se incluye
    tanto en avisos/create como en avisos/edit y así evitamos repetir código.

    Campos principales:
    - Título, contenido y tipo de aviso.
    - Destinatarios (audiencia): toda la comunidad, responsables, docentes,
      administración, un curso en particular o la familia de un alumno.
    - Confirmación de lectura, que es opcional y nunca bloquea el sistema.

    Variables que recibe: $aviso (opcional, solo al editar), $cursos y $estudiantes.
--}}
{{-- Si no llega un aviso (pantalla de creación) se deja en null para no generar errores --}}
@php($aviso = $aviso ?? null)

{{--
    Con Alpine.js guardamos la audiencia elegida para mostrar u ocultar los campos de
    curso o de alumno según corresponda, sin recargar la página.
--}}
<div x-data="{ audiencia: @js(old('audiencia', $aviso?->audiencia ?? 'todos')) }" class="space-y-4">
    {{-- Título y contenido del aviso; old() recupera lo escrito si la validación falla --}}
    <div>
        <x-input-label for="titulo" value="Título" />
        <x-text-input id="titulo" name="titulo" class="block mt-1 w-full" :value="old('titulo', $aviso?->titulo)" maxlength="180" required />
        <x-input-error :messages="$errors->get('titulo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="contenido" value="Contenido" />
        <textarea id="contenido" name="contenido" rows="6" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" required>{{ old('contenido', $aviso?->contenido) }}</textarea>
        <x-input-error :messages="$errors->get('contenido')" class="mt-2" />
    </div>

    {{-- Tipo de aviso y destinatarios; ambas listas salen de constantes del modelo Aviso --}}
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <x-input-label for="tipo" value="Tipo" />
            <select id="tipo" name="tipo" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                @foreach (\App\Models\Aviso::TIPOS as $value => $label)
                    <option value="{{ $value }}" @selected(old('tipo', $aviso?->tipo ?? 'institucional') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="audiencia" value="Destinatarios" />
            <select id="audiencia" name="audiencia" x-model="audiencia" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                @foreach (\App\Models\Aviso::AUDIENCIAS as $value => $label)
                    <option value="{{ $value }}" @selected(old('audiencia', $aviso?->audiencia ?? 'todos') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('audiencia')" class="mt-2" />
        </div>
    </div>

    {{-- Selector de curso: solo aparece cuando el aviso va dirigido a un curso específico --}}
    <div x-show="audiencia === 'curso'" x-cloak>
        <x-input-label for="curso_id" value="Curso" />
        <select id="curso_id" name="curso_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
            <option value="">— Seleccione un curso —</option>
            @foreach ($cursos ?? [] as $curso)
                <option value="{{ $curso->id }}" @selected(old('curso_id', $aviso?->curso_id) == $curso->id)>{{ $curso->etiqueta() }} · {{ $curso->nombreTurno() }}</option>
            @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">Reciben: responsables de los alumnos del curso y sus docentes asignados.</p>
        <x-input-error :messages="$errors->get('curso_id')" class="mt-2" />
    </div>

    {{-- Selector de alumno: solo aparece cuando el aviso es para la familia de un estudiante --}}
    <div x-show="audiencia === 'familia'" x-cloak>
        <x-input-label for="estudiante_id" value="Alumno" />
        <select id="estudiante_id" name="estudiante_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full">
            <option value="">— Seleccione un alumno —</option>
            @foreach ($estudiantes ?? [] as $estudiante)
                <option value="{{ $estudiante->id }}" @selected(old('estudiante_id', $aviso?->estudiante_id) == $estudiante->id)>{{ $estudiante->nombreCompleto() }}</option>
            @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">Reciben: los responsables registrados del alumno.</p>
        <x-input-error :messages="$errors->get('estudiante_id')" class="mt-2" />
    </div>

    {{--
        Opción para pedir confirmación de lectura y una fecha sugerida. Solo sirve para saber
        quién confirmó; los destinatarios pueden seguir usando el sistema aunque no confirmen.
    --}}
    <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="requiere_confirmacion" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm"
                @checked(old('requiere_confirmacion', $aviso?->requiere_confirmacion ?? false))>
            <span class="text-sm font-medium text-slate-800">Solicitar confirmación de lectura (opcional)</span>
        </label>
        <p class="text-xs text-slate-500 mt-1">
            La confirmación es <strong>opcional y no bloqueante</strong>: registra quién confirmó, pero el
            sistema se sigue usando con normalidad sin confirmar.
        </p>
        <div class="mt-3 max-w-xs">
            <x-input-label for="confirmar_antes" value="Fecha sugerida de confirmación (opcional)" />
            <x-text-input type="date" id="confirmar_antes" name="confirmar_antes" class="block mt-1 w-full"
                :value="old('confirmar_antes', $aviso?->confirmar_antes?->toDateString())" />
            <x-input-error :messages="$errors->get('confirmar_antes')" class="mt-2" />
        </div>
    </div>
</div>
