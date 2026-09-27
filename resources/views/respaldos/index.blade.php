<x-app-layout>
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

            {{-- §17: reglas de almacenamiento y restauración --}}
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

            <p class="text-xs text-slate-500">
                Recomendación operativa: genere un respaldo antes de cada importación masiva, cambio de gestión o
                actualización del sistema, y guarde una copia fuera del servidor (disco externo o nube institucional).
            </p>
        </div>
    </div>
</x-app-layout>
