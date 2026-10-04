{{--
    Vista: Panel principal (dashboard)
    Es la primera pantalla que ve cualquier usuario después de iniciar sesión.
    Muestra un resumen distinto según el rol: el responsable familiar ve solo
    la información de sus hijos (saldo, citaciones, avisos de pago), mientras
    que la dirección, secretaría y los docentes ven indicadores institucionales.

    Variables que recibe del controlador:
    - $stats: arreglo con los contadores del panel; solo trae las claves que el
      usuario tiene permitido ver, por eso en la vista se pregunta con array_key_exists.
    - $avisosRecientes, $avisosSinLeer, $avisosPorConfirmar: avisos institucionales
      dirigidos al usuario y sus contadores.
    - $misEstudiantes, $deudaPorHijo, $misAvisos, $misCitaciones: datos que solo
      se usan en el panel del responsable familiar.
--}}
<x-app-layout>
    {{-- Título de la página que se muestra en la cabecera del layout principal --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            Panel
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Mensaje de éxito que deja el controlador en la sesión después de alguna acción --}}
            @if (session('success'))
                <div class="bg-emerald-50 text-emerald-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            {{-- Saludo de bienvenida con el nombre del usuario y, si tiene, el rol asignado con Spatie --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-slate-600">Bienvenido, <strong>{{ auth()->user()->name }}</strong>
                    @if(auth()->user()->roles->first())
                        — rol: {{ auth()->user()->roles->first()->name }}
                    @endif
                </p>
            </div>

            {{--
                Avisos institucionales recibidos por el usuario. El bloque solo aparece
                si hay avisos recientes o alguno sin leer. Leerlos o confirmarlos es
                opcional: nunca se bloquea el uso del sistema por no hacerlo.
            --}}
            @if ($avisosRecientes->isNotEmpty() || $avisosSinLeer > 0)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                        <h3 class="font-semibold">Avisos recientes
                            @if ($avisosSinLeer > 0)
                                <span class="ml-1 text-xs bg-sky-100 text-sky-800 rounded-full px-2 py-0.5">{{ $avisosSinLeer }} sin leer</span>
                            @endif
                            @if ($avisosPorConfirmar > 0)
                                <span class="ml-1 text-xs bg-indigo-100 text-indigo-800 rounded-full px-2 py-0.5">{{ $avisosPorConfirmar }} con confirmación opcional</span>
                            @endif
                        </h3>
                        <a href="{{ route('avisos.index') }}" class="text-sky-700 text-sm hover:underline">Ver todos</a>
                    </div>
                    {{--
                        Recorremos los registros de destino (relación usuario-aviso). Los no leídos
                        se marcan con un punto azul y debajo se indica si ya se confirmó la lectura.
                    --}}
                    <ul class="space-y-2">
                        @foreach ($avisosRecientes as $destino)
                            <li class="flex justify-between gap-3 border-b border-slate-100 pb-2 text-sm">
                                <span>
                                    <a href="{{ route('avisos.show', $destino->aviso_id) }}" class="font-medium {{ $destino->leido_en ? 'text-slate-700' : 'text-slate-900' }} hover:underline">
                                        @if (! $destino->leido_en)
                                            <span class="inline-block w-2 h-2 rounded-full bg-sky-500 mr-1"></span>
                                        @endif
                                        {{ $destino->aviso->titulo }}
                                    </a>
                                    <span class="block text-xs text-slate-500">
                                        {{ $destino->aviso->nombreTipo() }} · {{ optional($destino->aviso->publicado_en)->format('d/m/Y H:i') }}
                                        @if ($destino->confirmado_en)
                                            · <span class="text-emerald-700">confirmado</span>
                                        @elseif ($destino->aviso->requiere_confirmacion)
                                            · <span class="text-indigo-600">confirmación opcional</span>
                                        @endif
                                    </span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    {{-- Nota aclaratoria para que el usuario sepa que confirmar no es obligatorio --}}
                    @if ($avisosPorConfirmar > 0)
                        <p class="text-xs text-slate-500 mt-3">
                            La confirmación de lectura es opcional: el sistema se usa con normalidad sin confirmar (§13).
                        </p>
                    @endif
                </div>
            @endif

            {{--
                A partir de aquí el panel se divide según el rol. Con @role verificamos si el
                usuario es Responsable Familiar; en ese caso solo ve la información de sus
                propios hijos. Cualquier otro rol cae en el bloque @else (panel institucional).
            --}}
            @role('Responsable Familiar')
                {{-- Panel del responsable familiar: tarjetas con sus indicadores personales --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                        <div class="text-sm text-slate-500">Saldo de aporte (§14)</div>
                        <div class="text-2xl font-semibold">{{ \App\Support\Dinero::formato($stats['saldo_aporte_centavos'] ?? 0) }}</div>
                    </div>
                    <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                        <div class="text-sm text-slate-500">Hijos vinculados</div>
                        <div class="text-2xl font-semibold">{{ $stats['mis_hijos'] ?? 0 }}</div>
                    </div>
                    <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                        <div class="text-sm text-slate-500">Citaciones pendientes</div>
                        <div class="text-2xl font-semibold">{{ $stats['citaciones_pendientes'] ?? 0 }}</div>
                    </div>
                    <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                        <div class="text-sm text-slate-500">Avisos de pago pendientes</div>
                        <div class="text-2xl font-semibold">{{ $stats['avisos_pago_pendientes'] ?? 0 }}</div>
                    </div>
                </div>

                {{--
                    Lista de hijos vinculados al responsable. Para cada uno buscamos su deuda en
                    $deudaPorHijo; si tiene saldo vencido se resalta en rojo para llamar la atención.
                    Además se ofrecen accesos directos a su estado de cuenta y a su historial.
                --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold mb-3">Mis representados</h3>
                    <ul class="space-y-2">
                        @forelse($misEstudiantes as $est)
                            @php($deuda = $deudaPorHijo->firstWhere('estudiante_id', $est->id))
                            <li class="flex justify-between border-b pb-2">
                                <span>
                                    {{ $est->nombreCompleto() }} — {{ $est->cursoActual()?->etiqueta() }}
                                    @if ($deuda)
                                        <span class="block text-xs {{ ($deuda['vencido'] ?? 0) > 0 ? 'text-rose-700' : 'text-slate-500' }}">
                                            Saldo: {{ \App\Support\Dinero::formato($deuda['saldo']) }}
                                            @if (($deuda['vencido'] ?? 0) > 0)
                                                · vencido {{ \App\Support\Dinero::formato($deuda['vencido']) }}
                                            @endif
                                        </span>
                                    @else
                                        <span class="block text-xs text-emerald-700">Sin saldo pendiente</span>
                                    @endif
                                </span>
                                <span class="flex gap-3">
                                    <a class="text-sky-700" href="{{ route('aporte.estado_cuenta', $est) }}">Estado de cuenta</a>
                                    <a class="text-sky-700" href="{{ route('historial.show', $est) }}">Historial</a>
                                </span>
                            </li>
                        @empty
                            <li class="text-slate-500">Sin estudiantes vinculados.</li>
                        @endforelse
                    </ul>

                    {{-- Últimos avisos de pago que informó el responsable, con un color según su estado --}}
                    @if ($misAvisos->isNotEmpty())
                        <h3 class="font-semibold mt-5 mb-3">Mis avisos de pago recientes</h3>
                        <ul class="space-y-2">
                            @foreach($misAvisos as $aviso)
                                @php($colores = ['pendiente' => 'bg-amber-100 text-amber-800', 'validado' => 'bg-emerald-100 text-emerald-800', 'rechazado' => 'bg-rose-100 text-rose-800', 'anulado' => 'bg-slate-200 text-slate-600'])
                                <li class="flex justify-between border-b pb-2 text-sm">
                                    <span>
                                        {{ optional($aviso->informado_en)->format('d/m/Y H:i') }} —
                                        {{ \App\Support\Dinero::formato($aviso->montoCentavos()) }}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $colores[$aviso->estado] ?? '' }}">{{ $aviso->nombreEstado() }}</span>
                                    </span>
                                    <a class="text-sky-700" href="{{ route('aporte.avisos.show', $aviso) }}">Ver</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Citaciones dirigidas al responsable, mostrando fecha, hora y motivo --}}
                    @if ($misCitaciones->isNotEmpty())
                        <h3 class="font-semibold mt-5 mb-3">Mis citaciones</h3>
                        <ul class="space-y-2">
                            @foreach($misCitaciones as $cit)
                                <li class="flex justify-between border-b pb-2 text-sm">
                                    <span>{{ optional($cit->fecha)->format('d/m/Y') }} {{ \Illuminate\Support\Str::of($cit->hora)->substr(0,5) }} — {{ $cit->motivo }}</span>
                                    <a class="text-sky-700" href="{{ route('citaciones.show', $cit) }}">Ver</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Enlaces rápidos a los módulos que el responsable familiar puede consultar --}}
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('aporte.avisos.index') }}" class="text-sky-700">Avisos de pago</a>
                        <a href="{{ route('aporte.pagos.index') }}" class="text-sky-700">Mis pagos</a>
                        <a href="{{ route('citaciones.index') }}" class="text-sky-700">Citaciones</a>
                        <a href="{{ route('avisos.index') }}" class="text-sky-700">Avisos</a>
                    </div>
                </div>
            @else
                {{--
                    Panel institucional (dirección, secretaría, docentes, etc.). Cada tarjeta se
                    muestra solo si el controlador envió esa clave en $stats, es decir, solo si el
                    rol del usuario tiene permiso para ver ese dato. Así un docente ve sus alumnos
                    y cursos, pero no los montos económicos de la unidad educativa.
                --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @if (array_key_exists('estudiantes', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Estudiantes activos</div>
                            <div class="text-2xl font-semibold">{{ $stats['estudiantes'] }}</div>
                        </div>
                    @endif
                    @if (array_key_exists('mis_alumnos', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Mis alumnos</div>
                            <div class="text-2xl font-semibold">{{ $stats['mis_alumnos'] }}</div>
                        </div>
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Mis cursos</div>
                            <div class="text-2xl font-semibold">{{ $stats['mis_cursos'] }}</div>
                        </div>
                    @endif
                    @if (array_key_exists('asistencias_hoy', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Asistencias hoy</div>
                            <div class="text-2xl font-semibold">{{ $stats['asistencias_hoy'] }}</div>
                        </div>
                    @endif
                    {{-- Las incidencias solo se muestran a quien tiene permiso sobre ese módulo --}}
                    @if (array_key_exists('incidencias_abiertas', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Incidencias abiertas</div>
                            <div class="text-2xl font-semibold">{{ $stats['incidencias_abiertas'] }}</div>
                        </div>
                    @endif
                    @if (array_key_exists('salidas_abiertas_hoy', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Salidas abiertas hoy</div>
                            <div class="text-2xl font-semibold">{{ $stats['salidas_abiertas_hoy'] }}</div>
                        </div>
                    @endif
                    @if (array_key_exists('salidas_abiertas', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Salidas abiertas (mis cursos)</div>
                            <div class="text-2xl font-semibold">{{ $stats['salidas_abiertas'] }}</div>
                        </div>
                    @endif
                    @if (array_key_exists('citaciones_pendientes', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Citaciones pendientes</div>
                            <div class="text-2xl font-semibold">{{ $stats['citaciones_pendientes'] }}</div>
                        </div>
                    @endif
                    {{-- Esta tarjeta solo aparece cuando realmente hay revisiones vencidas, y se pinta en rojo --}}
                    @if (array_key_exists('citaciones_revision_vencida', $stats) && $stats['citaciones_revision_vencida'] > 0)
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Revisiones vencidas</div>
                            <div class="text-2xl font-semibold text-rose-600">{{ $stats['citaciones_revision_vencida'] }}</div>
                        </div>
                    @endif
                    @if (array_key_exists('pagos_revision', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Pagos por confirmar</div>
                            <div class="text-2xl font-semibold">{{ $stats['pagos_revision'] }}</div>
                        </div>
                    @endif
                    {{--
                        Indicadores económicos del aporte. Solo los ve quien tiene permiso sobre el
                        módulo económico. Si hay avisos de pago pendientes, la tarjeta se resalta y
                        aparece un enlace directo para revisarlos.
                    --}}
                    @if (array_key_exists('avisos_pago_pendientes', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg {{ $stats['avisos_pago_pendientes'] > 0 ? 'border-2 border-amber-300' : '' }}">
                            <div class="text-sm text-slate-500">Avisos de pago por validar</div>
                            <div class="text-2xl font-semibold {{ $stats['avisos_pago_pendientes'] > 0 ? 'text-amber-700' : '' }}">{{ $stats['avisos_pago_pendientes'] }}</div>
                            @if ($stats['avisos_pago_pendientes'] > 0)
                                <a href="{{ route('aporte.avisos.index', ['estado' => 'pendiente']) }}" class="text-xs text-sky-700 hover:underline">Revisar</a>
                            @endif
                        </div>
                    @endif
                    @if (array_key_exists('aporte_recaudado_centavos', $stats))
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Aporte recaudado (gestión actual)</div>
                            <div class="text-2xl font-semibold text-emerald-700">{{ \App\Support\Dinero::formato($stats['aporte_recaudado_centavos']) }}</div>
                        </div>
                        <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="text-sm text-slate-500">Aporte vencido por cobrar</div>
                            <div class="text-2xl font-semibold text-rose-700">{{ \App\Support\Dinero::formato($stats['aporte_vencido_centavos']) }}</div>
                            <a href="{{ route('aporte.cuotas.index') }}" class="text-xs text-sky-700 hover:underline">Ver cuotas</a>
                        </div>
                    @endif
                </div>
            @endrole
        </div>
    </div>
</x-app-layout>
