{{--
    Vista: Listado de avisos institucionales
    Muestra los comunicados de la unidad educativa (reuniones, actividades,
    recordatorios). Cada usuario ve los avisos que le fueron dirigidos, y quien
    tiene permiso de gestión (dirección o secretaría) puede crear y editar avisos.

    Variables que recibe del controlador:
    - $avisos: avisos paginados visibles para el usuario.
    - $miEstado: estado de lectura y confirmación del usuario para cada aviso.
    - $porConfirmar: avisos que piden confirmación opcional y aún no se confirmaron.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Avisos</h2>
            {{-- Solo quien gestiona avisos puede crear uno nuevo --}}
            @can('avisos.gestionar')
                <a href="{{ route('avisos.create') }}"><x-primary-button type="button">Nuevo aviso</x-primary-button></a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.flash')

            {{--
                Recordatorio de los avisos que piden confirmar la lectura. Es solo informativo:
                confirmar es opcional y no impide seguir usando el sistema.
            --}}
            @if (isset($porConfirmar) && $porConfirmar->isNotEmpty())
                <div class="mb-4 bg-sky-50 border border-sky-200 rounded-lg p-4">
                    <div class="font-medium text-sky-900 text-sm mb-2">
                        Avisos con confirmación de lectura opcional ({{ $porConfirmar->count() }})
                    </div>
                    <p class="text-xs text-sky-700 mb-3">
                        Confirmar es opcional: el sistema se usa con normalidad sin hacerlo.
                    </p>
                    <ul class="space-y-1">
                        @foreach ($porConfirmar as $destino)
                            <li class="text-sm flex items-center justify-between gap-2">
                                <a href="{{ route('avisos.show', $destino->aviso_id) }}" class="text-sky-800 hover:underline">
                                    {{ $destino->aviso->titulo ?? 'Aviso' }}
                                </a>
                                @if ($destino->aviso && $destino->aviso->confirmar_antes)
                                    <span class="text-xs text-sky-600">sugerido: {{ $destino->aviso->confirmar_antes->format('d/m/Y') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{--
                Cada aviso se muestra como una tarjeta con su título, tipo, a quién va dirigido,
                la fecha y el autor. A la derecha se ven etiquetas de estado (publicado o borrador,
                leído, confirmado) y los enlaces para verlo o editarlo. El contenido se recorta a
                240 caracteres para que el listado no quede demasiado largo.
            --}}
            <div class="space-y-4">
                @forelse ($avisos as $aviso)
                    @php($estado = $miEstado->get($aviso->id))
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 mb-2">
                            <div>
                                <a href="{{ route('avisos.show', $aviso) }}" class="font-semibold text-slate-800 hover:underline">{{ $aviso->titulo }}</a>
                                <p class="text-xs text-slate-500 mt-1">
                                    {{ $aviso->nombreTipo() }} · {{ $aviso->descripcionAlcance() }}
                                    · {{ optional($aviso->publicado_en ?? $aviso->created_at)->format('d/m/Y H:i') }}
                                    @if ($aviso->creador)
                                        · {{ $aviso->creador->name }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap justify-end">
                                <span class="text-xs px-2 py-0.5 rounded {{ $aviso->publicado ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $aviso->publicado ? 'Publicado' : 'Borrador' }}
                                </span>
                                @if ($estado?->leido_en)
                                    <span class="text-xs px-2 py-0.5 rounded bg-sky-50 text-sky-700">Leído</span>
                                @endif
                                @if ($estado?->confirmado_en)
                                    <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">Confirmado</span>
                                @endif
                                <a href="{{ route('avisos.show', $aviso) }}" class="text-sky-700 text-sm hover:underline">Ver</a>
                                @can('avisos.gestionar')
                                    <a href="{{ route('avisos.edit', $aviso) }}" class="text-sky-700 text-sm hover:underline">Editar</a>
                                @endcan
                            </div>
                        </div>
                        <p class="text-sm text-slate-700 whitespace-pre-line">{{ Str::limit($aviso->contenido, 240) }}</p>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg p-6 text-center text-slate-500">
                        No hay avisos disponibles.
                    </div>
                @endforelse
            </div>
            {{-- Enlaces de paginación --}}
            <div class="mt-4">{{ $avisos->links() }}</div>
        </div>
    </div>
</x-app-layout>
