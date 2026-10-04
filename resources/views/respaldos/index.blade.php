{{--
    Vista: Respaldos de la base de datos.
    Desde esta pantalla el administrador genera copias de seguridad de la base de datos y las
    descarga. Es importante para no perder información ante una falla del equipo o un error.
    Recibe del controlador:
      - $respaldos: lista de respaldos generados (fecha, archivo, tamaño, tablas, estado, etc.).
      - $rutaFisica: carpeta privada del servidor donde se guardan los archivos.
      - $rutaDocs: ubicación del documento con los pasos para restaurar un respaldo.
    Solo la usan los usuarios con el permiso respaldos.gestionar.
--}}
<x-app-layout>
    {{-- Encabezado con el botón que genera un respaldo en el momento (formulario POST con CSRF). --}}
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Respaldos de la base de datos</h2>
            <form method="POST" action="{{ route('respaldos.store') }}">
                @csrf
                <x-primary-button>Generar respaldo ahora</x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('partials.flash')

            {{--
                Aviso sobre cómo se guardan y restauran los respaldos. Los archivos quedan fuera de
                la carpeta public, así nadie puede descargarlos con un enlace directo. La restauración
                no se ofrece desde la web porque reemplaza todos los datos; se hace por consola.
            --}}
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900">
                <p class="font-semibold mb-1">Almacenamiento privado</p>
                <p>
                    Los archivos se guardan en <code class="bg-amber-100 px-1 rounded">{{ $rutaFisica }}</code>
                    — <strong>fuera de <code>public/</code></strong>, sin URL accesible. La descarga pasa por el
                    sistema con permiso <code>respaldos.gestionar</code> y verifica el checksum SHA-256 antes de
                    entregar el archivo.
                </p>
                <p class="mt-2">
                    <strong>Restauración:</strong> es un procedimiento operativo por consola (no se ejecuta desde
                    la web porque es destructivo). Pasos documentados en
                    <code class="bg-amber-100 px-1 rounded">{{ $rutaDocs }}</code>.
                </p>
            </div>

            {{-- Tabla con el historial de respaldos generados. --}}
            <div class="bg-white shadow-sm rounded-lg p-6 overflow-x-auto">
                <h3 class="font-semibold text-slate-800 mb-3">Respaldos registrados</h3>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                            <th class="py-2 pr-3">Fecha</th>
                            <th class="py-2 pr-3">Archivo</th>
                            <th class="py-2 pr-3 text-right">Tamaño</th>
                            <th class="py-2 pr-3 text-right">Tablas</th>
                            <th class="py-2 pr-3">Base de datos</th>
                            <th class="py-2 pr-3">Estado</th>
                            <th class="py-2 pr-3">Generado por</th>
                            <th class="py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($respaldos as $respaldo)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3">{{ $respaldo->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-3 font-mono text-xs">{{ $respaldo->nombreDescarga() }}</td>
                                <td class="py-2 pr-3 text-right">{{ $respaldo->tamanoLegible() }}</td>
                                <td class="py-2 pr-3 text-right">{{ $respaldo->tablas }}</td>
                                <td class="py-2 pr-3 text-xs text-slate-500">{{ $respaldo->motor }} · {{ $respaldo->base_datos }}</td>
                                {{--
                                    Estado del respaldo: verde si se generó bien y rojo si falló (la directiva
                                    @class elige las clases según la condición). Si hubo error se muestra un
                                    resumen y el mensaje completo queda en el atributo title.
                                --}}
                                <td class="py-2 pr-3">
                                    <span class="text-xs px-2 py-0.5 rounded
                                        @class([
                                            'bg-emerald-50 text-emerald-700' => $respaldo->estado === 'ok',
                                            'bg-red-50 text-red-700' => $respaldo->estado !== 'ok',
                                        ])">{{ $respaldo->nombreEstado() }}</span>
                                    @if ($respaldo->error)
                                        <span class="block text-xs text-red-500 mt-0.5" title="{{ $respaldo->error }}">{{ Str::limit($respaldo->error, 60) }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-xs text-slate-500">{{ $respaldo->creador?->name ?? '—' }}</td>
                                {{--
                                    Acciones: solo se puede descargar un respaldo que terminó bien. La marca de
                                    checksum indica que el archivo tiene su huella SHA-256 para comprobar su integridad.
                                --}}
                                <td class="py-2 text-right space-x-2 whitespace-nowrap">
                                    @if ($respaldo->estado === 'ok')
                                        <a href="{{ route('respaldos.descargar', $respaldo) }}" class="text-sky-700 text-sm hover:underline">Descargar</a>
                                    @endif
                                    @if ($respaldo->checksum)
                                        <span class="text-xs text-slate-400" title="SHA-256: {{ $respaldo->checksum }}">checksum ✓</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-slate-500">
                                    Aún no hay respaldos. Genere el primero con el botón de arriba.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Recomendación de buenas prácticas para el uso de los respaldos. --}}
            <p class="text-xs text-slate-500">
                Recomendación operativa: genere un respaldo antes de cada importación masiva, cambio de gestión o
                actualización del sistema, y guarde una copia fuera del servidor (disco externo o nube institucional).
            </p>
        </div>
    </div>
</x-app-layout>
