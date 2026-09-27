<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Editar aviso</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($aviso->publicado)
                    <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm">
                        Este aviso ya está publicado. Si cambia los destinatarios y guarda,
                        se agregarán los nuevos; <strong>los destinatarios anteriores se conservan</strong>
                        (trazabilidad de a quién se avisó, §13).
                    </div>
                @endif
                <form method="POST" action="{{ route('avisos.update', $aviso) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    @include('avisos._form', ['aviso' => $aviso, 'cursos' => $cursos, 'estudiantes' => $estudiantes])
                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Actualizar</x-primary-button>
                        <a href="{{ route('avisos.show', $aviso) }}"><x-secondary-button type="button">Cancelar</x-secondary-button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
