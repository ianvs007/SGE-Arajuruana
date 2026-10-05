{{--
    Vista: Detalle de una citación
    Muestra todos los datos de una citación: cuándo es, a quién se convoca, el
    motivo, la incidencia asociada y los acuerdos de seguimiento. Quien gestiona
    citaciones puede además notificar al responsable y registrar la atención.

    Variables que recibe del controlador:
    - $citacion: la citación con sus relaciones (estudiante, padre, incidencia, etc.).
    - $verDetalleIncidencia: true si el usuario puede ver el detalle de la incidencia.
    - $enlaceWhatsApp: enlace wa.me con el mensaje preparado (puede venir vacío).
    - $puedeEnviarCorreo: indica si se muestra la opción de enviar correo.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Citación — {{ $citacion->estudiante?->nombreCompleto() }}</h2>
            {{-- El botón Editar solo aparece para quien tiene permiso de gestionar citaciones --}}
            <div class="flex gap-2">
                @can('citaciones.gestionar')
                    <a href="{{ route('citaciones.edit', $citacion) }}"><x-secondary-button type="button">Editar</x-secondary-button></a>
                @endcan
                <a href="{{ route('citaciones.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('partials.flash')

            {{--
                Datos principales de la citación con su estado en color. Si la revisión ya está
                vencida se muestra una etiqueta roja para que se atienda pronto.
            --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                @php($colores = ['pendiente' => 'bg-amber-100 text-amber-800', 'atendida' => 'bg-emerald-100 text-emerald-800', 'no_asistio' => 'bg-rose-100 text-rose-800', 'en_seguimiento' => 'bg-sky-100 text-sky-800', 'cerrada' => 'bg-slate-100 text-slate-600', 'cancelada' => 'bg-slate-100 text-slate-600'])
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold {{ $colores[$citacion->estado] ?? '' }}">{{ $citacion->nombreEstado() }}</span>
                    @if ($citacion->requiereRevision())
                        <span class="text-sm bg-rose-100 text-rose-800 rounded-full px-3 py-1">Revisión vencida: {{ $citacion->fecha_revision->format('d/m/Y') }}</span>
                    @endif
                </div>

                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Convocatoria</dt>
                        <dd class="font-medium">{{ optional($citacion->fecha)->format('d/m/Y') }} a horas {{ \Illuminate\Support\Str::of($citacion->hora)->substr(0, 5) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Responsable convocado</dt>
                        <dd class="font-medium">{{ $citacion->padre?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Alumno</dt>
                        <dd class="font-medium">{{ $citacion->estudiante?->nombreCompleto() }} — {{ $citacion->estudiante?->cursoActual()?->etiqueta() ?? 'sin curso' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Emitida por</dt>
                        <dd class="font-medium">{{ $citacion->generador?->name ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Motivo</dt>
                        <dd class="font-medium">{{ $citacion->motivo }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Mensaje dirigido al responsable</dt>
                        <dd class="font-medium whitespace-pre-line">{{ $citacion->descripcion ?: '—' }}</dd>
                    </div>

                    {{--
                        Incidencia asociada. El detalle completo solo se muestra a quien tiene permiso;
                        los demás, por ejemplo el responsable familiar, solo ven que es un caso reservado.
                    --}}
                    @if ($citacion->incidencia_id)
                        <div class="sm:col-span-2 border-t border-slate-100 pt-3">
                            <dt class="text-slate-500">Incidencia asociada</dt>
                            @if ($verDetalleIncidencia)
                                <dd class="font-medium">
                                    {{ optional($citacion->incidencia->fecha)->format('d/m/Y') }} — {{ $citacion->incidencia->etiquetaPublica() }}
                                    ({{ $citacion->incidencia->nombreEstado() }})
                                    @if ($citacion->incidencia->confidencial)
                                        <span class="ml-1 text-[11px] bg-violet-100 text-violet-800 rounded px-1.5 py-0.5">Confidencial</span>
                                    @endif
                                    <div class="mt-1 text-slate-600 whitespace-pre-line">{{ $citacion->incidencia->descripcion }}</div>
                                </dd>
                            @else
                                {{-- Caso confidencial: solo Administración ve el detalle, aquí se muestra únicamente una referencia --}}
                                <dd class="font-medium text-slate-500">Caso reservado gestionado por Administración.</dd>
                            @endif
                        </div>
                    @endif
                </dl>
            </div>

            {{--
                Notificación al responsable, solo para quien gestiona citaciones. WhatsApp abre la
                aplicación con el mensaje listo (el envío es manual) y el correo es opcional; el
                botón de correo se deshabilita si el responsable no tiene correo registrado.
            --}}
            @can('citaciones.gestionar')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-1">Notificar al responsable</h3>
                    <p class="text-xs text-slate-500 mb-4">
                        Ambos medios son de apoyo: la citación queda registrada en el sistema y visible
                        para el responsable desde su cuenta. WhatsApp abre la app con el mensaje listo
                        (envío manual, sin API); el correo es opcional y su fallo no bloquea nada.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        @if (! empty($enlaceWhatsApp))
                            <a href="{{ $enlaceWhatsApp }}" target="_blank" rel="noopener noreferrer">
                                <x-secondary-button type="button">Abrir WhatsApp (envío manual)</x-secondary-button>
                            </a>
                        @endif
                        @if ($puedeEnviarCorreo)
                            <form method="POST" action="{{ route('citaciones.correo', $citacion) }}">
                                @csrf
                                <x-secondary-button type="submit"
                                    :disabled="empty($citacion->padre?->email)">Enviar correo</x-secondary-button>
                            </form>
                            @if (empty($citacion->padre?->email))
                                <span class="self-center text-xs text-slate-500">El responsable no tiene correo registrado.</span>
                            @endif
                        @endif
                    </div>
                    @if ($citacion->incidencia?->confidencial && ! $verDetalleIncidencia)
                        <p class="text-xs text-slate-500 mt-3">
                            La citación proviene de un caso reservado: el mensaje compartido no incluye su detalle.
                        </p>
                    @endif
                </div>
            @endcan

            {{--
                Acuerdos y seguimiento: quién se encarga, cuándo se revisa y lo que se acordó.
                Los acuerdos y observaciones solo se muestran si ya fueron registrados.
            --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-slate-800">Acuerdos y seguimiento</h3>

                <div class="grid sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-slate-500 block">Responsable del seguimiento</span>
                        <span class="font-medium">{{ $citacion->seguimientoResponsable?->name ?? 'Sin asignar' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Fecha de revisión</span>
                        <span class="font-medium">{{ $citacion->fecha_revision?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Última actualización</span>
                        <span class="font-medium">{{ $citacion->updated_at?->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                @if ($citacion->acuerdos)
                    <div>
                        <span class="text-slate-500 text-sm block mb-1">Acuerdos</span>
                        <p class="text-sm bg-emerald-50 border border-emerald-200 rounded p-3 whitespace-pre-line">{{ $citacion->acuerdos }}</p>
                    </div>
                @endif

                @if ($citacion->observaciones_seguimiento)
                    <div>
                        <span class="text-slate-500 text-sm block mb-1">Observaciones de seguimiento</span>
                        <p class="text-sm bg-slate-50 border border-slate-200 rounded p-3 whitespace-pre-line">{{ $citacion->observaciones_seguimiento }}</p>
                    </div>
                @endif

                {{--
                    Formulario rápido para registrar el resultado de la reunión (atendida, no asistió,
                    en seguimiento o cerrada) sin tener que entrar a editar toda la citación.
                --}}
                @can('citaciones.gestionar')
                    <form method="POST" action="{{ route('citaciones.seguimiento', $citacion) }}" class="border-t border-slate-100 pt-4 space-y-3">
                        @csrf
                        <h4 class="text-sm font-semibold text-slate-700">Registrar atención / seguimiento</h4>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="seg_estado" value="Resultado" />
                                <select id="seg_estado" name="estado" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 w-full" required>
                                    @foreach (['atendida' => 'Atendida', 'no_asistio' => 'No asistió', 'en_seguimiento' => 'En seguimiento', 'cerrada' => 'Cerrada'] as $value => $label)
                                        <option value="{{ $value }}" @selected($citacion->estado === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('estado')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="seg_fecha_revision" value="Próxima revisión" />
                                <x-text-input id="seg_fecha_revision" type="date" name="fecha_revision" class="block mt-1 w-full" :value="old('fecha_revision', optional($citacion->fecha_revision)->format('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('fecha_revision')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="seg_acuerdos" value="Acuerdos" />
                            <textarea id="seg_acuerdos" name="acuerdos" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('acuerdos', $citacion->acuerdos) }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="seg_observaciones" value="Observaciones" />
                            <textarea id="seg_observaciones" name="observaciones_seguimiento" rows="2" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('observaciones_seguimiento', $citacion->observaciones_seguimiento) }}</textarea>
                        </div>
                        <x-primary-button>Guardar seguimiento</x-primary-button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
