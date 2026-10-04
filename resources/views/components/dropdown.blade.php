{{--
    Componente: x-dropdown
    Menú desplegable que usamos en la barra de navegación, por ejemplo en el menú
    del usuario (perfil y cerrar sesión). Funciona con Alpine.js.

    Props y slots que recibe:
    - align: hacia dónde se abre el menú (left, top o right por defecto).
    - width: ancho del menú; "48" equivale a la clase w-48.
    - contentClasses: clases extra para el contenedor de las opciones.
    - Slot "trigger": el botón o texto que abre el menú.
    - Slot "content": las opciones del menú (normalmente x-dropdown-link).
--}}
@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white'])

{{-- Calculamos las clases de alineación y de ancho según las props recibidas --}}
@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

{{-- El estado "open" controla si el menú está visible; se cierra al hacer clic fuera de él --}}
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    {{-- Disparador: al hacer clic alterna entre abrir y cerrar el menú --}}
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    {{-- Panel con las opciones, con una pequeña animación al aparecer y desaparecer --}}
    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
