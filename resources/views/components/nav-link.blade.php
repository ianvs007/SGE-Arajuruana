{{--
    Componente: x-nav-link
    Enlace del menú de navegación superior en pantallas grandes. Se reutiliza en
    la barra de navegación para cada módulo del sistema.

    Props: active (booleano) indica si el enlace corresponde a la página actual,
    para resaltarlo con un borde inferior. Los demás atributos (href, etc.) se
    pasan directamente a la etiqueta <a>.
--}}
@props(['active'])

{{-- Elegimos las clases según si el enlace está activo o no --}}
@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
