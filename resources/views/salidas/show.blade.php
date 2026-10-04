{{--
    Vista: Detalle de una salida.
    Muestra todo el recorrido de una salida de alumno en tres pasos: autorización, salida
    efectiva y retorno. Además de los datos de la salida, según el estado y los permisos del
    usuario aparecen los formularios para registrar la salida efectiva, cancelar la autorización
    o registrar el retorno del alumno.
    Recibe del controlador:
      - $salida: la salida con su estudiante y los usuarios que intervinieron en cada paso.
      - $puedeRegistrar: indica si el usuario puede registrar la salida efectiva y el retorno.
    La usan el personal que autoriza salidas y la Administración, que registra los demás pasos.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Detalle de salida</h2>
            <a href="{{ route('salidas.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{--
                Línea de estados. Separamos tres momentos distintos: que se autorice la salida no
                significa que el alumno ya se fue, y que se haya ido no significa que ya volvió.
                Cada paso se pinta de verde cuando ya ocurrió y muestra quién lo registró y cuándo.
            --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                @php($colores = ['autorizada' => 'bg-amber-100 text-amber-800', 'salida_efectiva' => 'bg-sky-100 text-sky-800', 'retornada' => 'bg-emerald-100 text-emerald-800', 'cancelada' => 'bg-slate-200 text-slate-600'])
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-slate-500">Estado actual:</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full font-semibold {{ $colores[$salida->estado] ?? '' }}">{{ $salida->nombreEstado() }}</span>
                </div>
                <ol class="mt-4 grid sm:grid-cols-3 gap-3 text-xs">
                    {{-- Paso 1: autorización. --}}
                    <li class="rounded border {{ $salida->autorizado_en ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200' }} p-3">
                        <div class="font-semibold text-slate-700">1. Autorización</div>
                        @if ($salida->autorizado_en)
                            <div class="mt-1 text-slate-600">{{ $salida->autorizante?->name }} · {{ $salida->autorizado_en->format('d/m/Y H:i') }}</div>
                        @else
                            <div class="mt-1 text-slate-400">Pendiente</div>
                        @endif
                    </li>
                    {{-- Paso 2: salida efectiva, con la hora real en que el alumno se retiró. --}}
                    <li class="rounded border {{ $salida->salida_en ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200' }} p-3">
                        <div class="font-semibold text-slate-700">2. Salida efectiva</div>
                        @if ($salida->salida_en)
                            <div class="mt-1 text-slate-600">
                                {{ $salida->hora_salida ? \Illuminate\Support\Str::of($salida->hora_salida)->substr(0, 5) : '' }} ·
                                registra {{ $salida->registroSalida?->name }} · {{ $salida->salida_en->format('d/m/Y H:i') }}
                            </div>
                        @else
                            <div class="mt-1 text-slate-400">Aún no se retiró</div>
                        @endif
                    </li>
                    {{-- Paso 3: retorno. Si la salida fue cancelada, este paso ya no aplica. --}}
                    <li class="rounded border {{ $salida->retorno_en ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200' }} p-3">
                        <div class="font-semibold text-slate-700">3. Retorno</div>
                        @if ($salida->retorno_en)
                            <div class="mt-1 text-slate-600">
                                {{ $salida->hora_retorno ? \Illuminate\Support\Str::of($salida->hora_retorno)->substr(0, 5) : '' }} ·
                                registra {{ $salida->registroRetorno?->name }}
                            </div>
                        @else
                            <div class="mt-1 text-slate-400">{{ $salida->estado === 'cancelada' ? 'No aplica' : 'Pendiente' }}</div>
                        @endif
                    </li>
                </ol>
            </div>

            {{-- Datos de la salida: estudiante, curso, fecha, motivo, persona que retira y verificación realizada. --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Estudiante</dt>
                        <dd class="font-medium">{{ $salida->estudiante?->nombreCompleto() }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Curso</dt>
                        <dd class="font-medium">{{ $salida->estudiante?->cursoActual()?->etiqueta() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Fecha</dt>
                        <dd class="font-medium">{{ optional($salida->fecha)->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Motivo</dt>
                        <dd class="font-medium">{{ $salida->nombreMotivo() }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Persona que retira</dt>
                        <dd class="font-medium">{{ $salida->responsable_retiro ?? '— (se registra con la salida efectiva)' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Documento</dt>
                        <dd class="font-medium">{{ $salida->documento_responsable ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Verificación del retiro (manual)</dt>
                        <dd class="font-medium">{{ $salida->verificacion_retiro ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Observación</dt>
                        <dd class="font-medium">{{ $salida->observacion ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Zona de acciones: solo se muestra si el usuario puede registrar pasos o si la salida sigue autorizada. --}}
            @if ($puedeRegistrar || $salida->estado === 'autorizada')
                <div class="grid lg:grid-cols-2 gap-6">
                    {{--
                        Registrar la salida efectiva: lo hace solo Administración y únicamente mientras la
                        salida está autorizada. Aquí se anota la hora real y los datos de quien recoge al alumno.
                    --}}
                    @if ($salida->estado === 'autorizada')
                        @can('salidas.registrar')
                            <div class="bg-white shadow-sm rounded-lg p-6">
                                <h3 class="font-semibold text-slate-800 mb-1">Registrar salida efectiva</h3>
                                <p class="text-xs text-slate-500 mb-4">
                                    Verifique a la vista el documento de la persona que retira. El registro es manual
                                    y no otorga validez institucional automática al parentesco.
                                </p>
                                <form method="POST" action="{{ route('salidas.salida-efectiva', $salida) }}" class="space-y-3">
                                    @csrf
                                    <div>
                                        <x-input-label for="hora_salida" value="Hora efectiva de salida" />
                                        <x-text-input id="hora_salida" type="time" name="hora_salida" class="block mt-1 w-full" :value="old('hora_salida', now()->format('H:i'))" required />
                                        <x-input-error :messages="$errors->get('hora_salida')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="responsable_retiro" value="Persona que retira (nombre completo)" />
                                        <x-text-input id="responsable_retiro" name="responsable_retiro" class="block mt-1 w-full" :value="old('responsable_retiro')" required />
                                        <x-input-error :messages="$errors->get('responsable_retiro')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="documento_responsable" value="Documento de la persona (C.I.)" />
                                        <x-text-input id="documento_responsable" name="documento_responsable" class="block mt-1 w-full" :value="old('documento_responsable')" />
                                        <x-input-error :messages="$errors->get('documento_responsable')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="verificacion_retiro" value="Verificación realizada (opcional)" />
                                        <x-text-input id="verificacion_retiro" name="verificacion_retiro" class="block mt-1 w-full" :value="old('verificacion_retiro', 'Documento verificado a la vista por Administración')" />
                                        <x-input-error :messages="$errors->get('verificacion_retiro')" class="mt-2" />
                                    </div>
                                    <x-primary-button>Registrar salida efectiva</x-primary-button>
                                </form>
                            </div>
                        @endcan

                        {{-- Cancelar la autorización: para quien autoriza salidas, mientras el alumno no se haya retirado. Exige indicar el motivo. --}}
                        @can('salidas.autorizar')
                            <div class="bg-white shadow-sm rounded-lg p-6">
                                <h3 class="font-semibold text-slate-800 mb-1">Cancelar autorización</h3>
                                <p class="text-xs text-slate-500 mb-4">
                                    Solo si el alumno aún no se retiró. La cancelación queda documentada y auditada.
                                </p>
                                <form method="POST" action="{{ route('salidas.cancelar', $salida) }}" class="space-y-3"
                                    onsubmit="return confirm('¿Cancelar esta autorización de salida?')">
                                    @csrf
                                    <div>
                                        <x-input-label for="motivo_cancelacion" value="Motivo de la cancelación" />
                                        <x-text-input id="motivo_cancelacion" name="motivo_cancelacion" class="block mt-1 w-full" required />
                                        <x-input-error :messages="$errors->get('motivo_cancelacion')" class="mt-2" />
                                    </div>
                                    <x-danger-button>Cancelar autorización</x-danger-button>
                                </form>
                            </div>
                        @endcan
                    @endif

                    {{--
                        Registrar el retorno: también lo hace solo Administración, cuando el alumno ya salió.
                        Se recuerda la hora de salida porque el retorno no puede ser anterior a ella.
                    --}}
                    @if ($salida->estado === 'salida_efectiva' && $puedeRegistrar)
                        <div class="bg-white shadow-sm rounded-lg p-6 lg:col-span-2">
                            <h3 class="font-semibold text-slate-800 mb-1">Registrar retorno</h3>
                            <p class="text-xs text-slate-500 mb-4">
                                El retorno no puede ser anterior a la hora de salida ({{ \Illuminate\Support\Str::of($salida->hora_salida)->substr(0, 5) }}).
                            </p>
                            <form method="POST" action="{{ route('salidas.retorno', $salida) }}" class="grid sm:grid-cols-2 gap-3 items-end">
                                @csrf
                                <div>
                                    <x-input-label for="hora_retorno" value="Hora de retorno" />
                                    <x-text-input id="hora_retorno" type="time" name="hora_retorno" class="block mt-1 w-full" :value="old('hora_retorno', now()->format('H:i'))" required />
                                    <x-input-error :messages="$errors->get('hora_retorno')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="observacion_retorno" value="Observación (opcional)" />
                                    <x-text-input id="observacion_retorno" name="observacion" class="block mt-1 w-full" :value="old('observacion')" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-primary-button>Registrar retorno</x-primary-button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
