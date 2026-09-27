<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Configurar curso: {{ $curso->etiqueta() }}</h2>
            <a href="{{ route('cursos.index', ['gestion_id' => $gestion->id]) }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{-- Datos del curso --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-4">Datos del curso</h3>
                <form method="POST" action="{{ route('cursos.update', $curso) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="gestion_id" value="{{ $curso->gestion_id }}">
                    @include('cursos._form')
                    <x-primary-button>Guardar cambios</x-primary-button>
                </form>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                {{-- Docentes asignados (§5) --}}
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Docentes asignados</h3>
                    <ul class="divide-y divide-slate-100 mb-4">
                        @forelse ($curso->docentes as $docente)
                            <li class="py-2 flex justify-between items-center gap-2">
                                <span class="text-sm text-slate-700">{{ $docente->name }}
                                    <span class="text-slate-400">({{ $docente->pivot->rol_docente }})</span>
                                </span>
                                <form method="POST" action="{{ route('cursos.docentes.destroy', [$curso, $docente]) }}"
                                    onsubmit="return confirm('¿Retirar al docente de este curso?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 text-sm hover:underline">Retirar</button>
                                </form>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-slate-500">Sin docentes asignados.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('cursos.docentes.store', $curso) }}" class="flex gap-2">
                        @csrf
                        <select name="user_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm flex-1" required>
                            <option value="">Seleccione docente…</option>
                            @foreach ($docentesDisponibles as $docente)
                                @unless ($curso->docentes->contains($docente->id))
                                    <option value="{{ $docente->id }}">{{ $docente->name }}</option>
                                @endunless
                            @endforeach
                        </select>
                        <x-primary-button>Agregar</x-primary-button>
                    </form>
                </div>

                {{-- Horarios (§4: vigentes desde su fecha; no reinterpretan el pasado) --}}
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Horarios de clases</h3>
                    <p class="text-xs text-slate-500 mb-3">Los cambios de horario rigen hacia adelante: no reinterpretan asistencias ya registradas.</p>
                    <ul class="divide-y divide-slate-100 mb-4">
                        @forelse ($horarios as $horario)
                            <li class="py-2 flex justify-between items-center gap-2">
                                <span class="text-sm text-slate-700">
                                    {{ $horario->nombreDia() }} — {{ $horario->nombreTurno() }}
                                    @if ($horario->hora_inicio && $horario->hora_fin)
                                        <span class="text-slate-500">{{ substr($horario->hora_inicio, 0, 5) }} a {{ substr($horario->hora_fin, 0, 5) }}</span>
                                    @endif
                                </span>
                                <form method="POST" action="{{ route('cursos.horarios.destroy', [$curso, $horario]) }}"
                                    onsubmit="return confirm('¿Eliminar este horario?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 text-sm hover:underline">Eliminar</button>
                                </form>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-slate-500">Sin horarios definidos.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('cursos.horarios.store', $curso) }}" class="grid grid-cols-2 sm:grid-cols-5 gap-2 items-end">
                        @csrf
                        <div>
                            <x-input-label for="dia_semana" value="Día" class="text-xs" />
                            <select id="dia_semana" name="dia_semana" class="border-gray-300 rounded-md shadow-sm text-sm mt-1 w-full" required>
                                @foreach (\App\Models\HorarioCurso::DIAS as $num => $nombre)
                                    <option value="{{ $num }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="turno_horario" value="Turno" class="text-xs" />
                            <select id="turno_horario" name="turno" class="border-gray-300 rounded-md shadow-sm text-sm mt-1 w-full" required>
                                @foreach (\App\Models\HorarioCurso::TURNOS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="hora_inicio" value="Inicio" class="text-xs" />
                            <x-text-input id="hora_inicio" type="time" name="hora_inicio" class="block mt-1 w-full text-sm" />
                        </div>
                        <div>
                            <x-input-label for="hora_fin" value="Fin" class="text-xs" />
                            <x-text-input id="hora_fin" type="time" name="hora_fin" class="block mt-1 w-full text-sm" />
                        </div>
                        <x-primary-button class="w-full">Agregar</x-primary-button>
                    </form>
                </div>
            </div>

            {{-- Calendario (§4, §9) --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-slate-800 mb-3">Calendario — jornadas sin clases</h3>
                <p class="text-xs text-slate-500 mb-3">
                    Estas jornadas no generan ausencia: «sin clases programadas» no equivale a ausente.
                </p>
                <div class="overflow-x-auto mb-4">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2 pr-3">Fecha</th>
                                <th class="pr-3">Tipo</th>
                                <th class="pr-3">Alcance</th>
                                <th class="pr-3">Motivo</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($excepciones as $excepcion)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2 pr-3">{{ $excepcion->fecha->format('d/m/Y') }}</td>
                                    <td class="pr-3">{{ $excepcion->nombreTipo() }}</td>
                                    <td class="pr-3">{{ $excepcion->curso_id ? 'Solo este curso' : 'Toda la gestión' }}</td>
                                    <td class="pr-3 text-slate-600">{{ $excepcion->motivo ?? '—' }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('cursos.excepciones.destroy', [$curso, $excepcion]) }}" class="inline"
                                            onsubmit="return confirm('¿Eliminar esta jornada del calendario?')">
                                            @csrf @method('DELETE')
                                            <button class="text-rose-600 text-sm hover:underline">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-500">Sin jornadas especiales registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('cursos.excepciones.store', $curso) }}" class="grid sm:grid-cols-5 gap-2 items-end">
                    @csrf
                    <div>
                        <x-input-label for="fecha_excepcion" value="Fecha" class="text-xs" />
                        <x-text-input id="fecha_excepcion" type="date" name="fecha" class="block mt-1 w-full text-sm" required />
                    </div>
                    <div>
                        <x-input-label for="tipo_excepcion" value="Tipo" class="text-xs" />
                        <select id="tipo_excepcion" name="tipo" class="border-gray-300 rounded-md shadow-sm text-sm mt-1 w-full" required>
                            @foreach (\App\Models\CalendarioExcepcion::TIPOS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="motivo_excepcion" value="Motivo" class="text-xs" />
                        <x-text-input id="motivo_excepcion" name="motivo" class="block mt-1 w-full text-sm" placeholder="Opcional" maxlength="255" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" name="aplica_solo_curso" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                            Solo este curso
                        </label>
                        <x-primary-button class="w-full sm:w-auto">Registrar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
