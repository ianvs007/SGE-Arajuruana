<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ $aviso->titulo }}</h2>
            <a href="{{ route('avisos.index') }}"><x-secondary-button type="button">Volver</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center gap-2 flex-wrap mb-3">
                    <span class="text-xs px-2 py-0.5 rounded {{ $aviso->publicado ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $aviso->publicado ? 'Publicado' : 'Borrador' }}
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600">{{ $aviso->nombreTipo() }}</span>
                    <span class="text-xs text-slate-500">
                        Dirigido a: {{ $aviso->descripcionAlcance() }}
                        · {{ optional($aviso->publicado_en ?? $aviso->created_at)->format('d/m/Y H:i') }}
                        @if ($aviso->creador)
                            · {{ $aviso->creador->name }}
                        @endif
                    </span>
                </div>

                <p class="text-sm text-slate-800 whitespace-pre-line">{{ $aviso->contenido }}</p>

                {{-- Confirmación de lectura OPCIONAL del destinatario (§13) --}}
                @if ($miDestino)
                    <div class="mt-4 border-t border-slate-200 pt-4 flex flex-wrap items-center gap-3">
                        @if ($aviso->requiere_confirmacion)
                            @if ($miDestino->confirmado_en)
                                <span class="text-sm text-emerald-700">
                                    ✔ Confirmaste la lectura el {{ $miDestino->confirmado_en->format('d/m/Y H:i') }}
                                </span>
                            @else
                                <form method="POST" action="{{ route('avisos.confirmar', $aviso) }}">
                                    @csrf
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <x-primary-button>Confirmar lectura (opcional)</x-primary-button>
                                        <span class="text-xs text-slate-500">
                                            No es obligatorio: el sistema se usa igual sin confirmar (§13).
                                            @if ($aviso->confirmar_antes)
                                                Fecha sugerida: {{ $aviso->confirmar_antes->format('d/m/Y') }}.
                                            @endif
                                        </span>
                                    </div>
                                </form>
                            @endif
                        @elseif (! $miDestino->leido_en)
                            <span class="text-sm text-slate-500">Este aviso no requiere confirmación de lectura.</span>
                        @endif
                    </div>
                @endif
            </div>

            @if ($esEmisor)
                {{-- Herramientas del emisor: publicar, correo opcional y WhatsApp manual (§13) --}}
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Difusión</h3>

                    @if (! $aviso->publicado)
                        <form method="POST" action="{{ route('avisos.publicar', $aviso) }}" class="mb-4">
                            @csrf
                            <x-primary-button>Publicar y definir destinatarios</x-primary-button>
                            <p class="text-xs text-slate-500 mt-2">
                                Al publicar se materializa la lista de destinatarios según el alcance
                                (queda el registro de a quién se avisó, aunque cambien inscripciones después).
                            </p>
                        </form>
                    @else
                        <div class="flex flex-wrap gap-3 mb-4">
                            <form method="POST" action="{{ route('avisos.correo', $aviso) }}">
                                @csrf
                                <x-secondary-button type="submit">Enviar correo (opcional)</x-secondary-button>
                            </form>
                            {{-- WhatsApp MANUAL: enlace wa.me con texto precargado; el
                                 envío lo hace el usuario desde su teléfono (§13). --}}
                            <a href="{{ $enlaceWhatsApp }}" target="_blank" rel="noopener noreferrer">
                                <x-secondary-button type="button">Abrir WhatsApp (envío manual)</x-secondary-button>
                            </a>
                            <a href="{{ route('avisos.edit', $aviso) }}"><x-secondary-button type="button">Editar</x-secondary-button></a>
                        </div>
                        <p class="text-xs text-slate-500">
                            El correo es opcional: si falla, el aviso sigue visible en el sistema.
                            WhatsApp abre la app con el mensaje listo; el envío lo decide usted (no hay API).
                        </p>
                    @endif

                    {{-- Progreso de confirmación (solo informativo; nunca bloquea, §13) --}}
                    @if ($aviso->publicado && $aviso->requiere_confirmacion)
                        <div class="mt-4 border-t border-slate-200 pt-4">
                            <h4 class="text-sm font-semibold text-slate-700 mb-2">Confirmaciones (opcional)</h4>
                            <div class="grid grid-cols-3 gap-3 text-center mb-2">
                                <div class="bg-slate-50 rounded p-2">
                                    <div class="text-lg font-bold text-slate-800">{{ $progreso['total'] }}</div>
                                    <div class="text-xs text-slate-500">destinatarios</div>
                                </div>
                                <div class="bg-sky-50 rounded p-2">
                                    <div class="text-lg font-bold text-sky-800">{{ $progreso['leidos'] }}</div>
                                    <div class="text-xs text-sky-600">leyeron</div>
                                </div>
                                <div class="bg-indigo-50 rounded p-2">
                                    <div class="text-lg font-bold text-indigo-800">{{ $progreso['confirmados'] }}</div>
                                    <div class="text-xs text-indigo-600">confirmaron</div>
                                </div>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2">
                                <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $progreso['porcentaje_leidos'] }}%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">
                                {{ $progreso['porcentaje_leidos'] }}% de lectura. Quien no confirme sigue usando el sistema con normalidad (§13).
                            </p>
                        </div>
                    @endif

                    {{-- Destinatarios materializados con estado de correo (§13) --}}
                    @if ($aviso->publicado)
                        <div class="mt-4 border-t border-slate-200 pt-4">
                            <h4 class="text-sm font-semibold text-slate-700 mb-2">
                                Destinatarios ({{ $destinatarios->count() }})
                            </h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                                            <th class="py-2 pr-3">Usuario</th>
                                            <th class="py-2 pr-3">Motivo</th>
                                            <th class="py-2 pr-3">Lectura</th>
                                            <th class="py-2 pr-3">Confirmación</th>
                                            <th class="py-2 pr-3">Correo</th>
                                            <th class="py-2">WhatsApp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($destinatarios as $destino)
                                            <tr class="border-b border-slate-100">
                                                <td class="py-2 pr-3">{{ $destino->usuario?->name ?? '—' }}</td>
                                                <td class="py-2 pr-3 text-xs text-slate-500">{{ $destino->motivo }}</td>
                                                <td class="py-2 pr-3 text-xs">
                                                    {{ $destino->leido_en ? $destino->leido_en->format('d/m/Y H:i') : '—' }}
                                                </td>
                                                <td class="py-2 pr-3 text-xs">
                                                    @if ($destino->confirmado_en)
                                                        <span class="text-emerald-700">{{ $destino->confirmado_en->format('d/m/Y H:i') }}</span>
                                                    @else
                                                        <span class="text-slate-400">pendiente (opcional)</span>
                                                    @endif
                                                </td>
                                                <td class="py-2 pr-3 text-xs">
                                                    <span class="px-1.5 py-0.5 rounded
                                                        @class([
                                                            'bg-emerald-50 text-emerald-700' => $destino->correo_estado === 'enviado',
                                                            'bg-red-50 text-red-700' => $destino->correo_estado === 'error',
                                                            'bg-slate-100 text-slate-500' => $destino->correo_estado === 'omitido' || ! $destino->correo_estado,
                                                        ])">{{ $destino->nombreEstadoCorreo() }}</span>
                                                    @if ($destino->correo_estado === 'error' && $destino->correo_error)
                                                        <span class="block text-red-500 mt-0.5" title="{{ $destino->correo_error }}">falló el envío</span>
                                                    @endif
                                                </td>
                                                <td class="py-2 text-xs">
                                                    @if ($destino->usuario?->telefono)
                                                        {{-- Enlace manual por destinatario (§13): la app no envía --}}
                                                        <a class="text-emerald-700 hover:underline" target="_blank" rel="noopener noreferrer"
                                                           href="https://wa.me/{{ \App\Support\WhatsApp::normalizarTelefono($destino->usuario->telefono) }}?text={{ rawurlencode(\App\Support\WhatsApp::textoAviso($aviso)) }}">
                                                            wa.me/{{ \App\Support\WhatsApp::normalizarTelefono($destino->usuario->telefono) }}
                                                        </a>
                                                    @else
                                                        <span class="text-slate-400">sin teléfono</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
