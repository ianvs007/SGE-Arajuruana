{{--
    Componente: x-secondary-button
    Botón secundario (blanco con borde) para acciones de menor importancia, como
    "Cancelar" dentro de un modal. Por defecto es de tipo "button", así que no
    envía el formulario a menos que se le indique otro tipo.

    Atributos: cualquier atributo HTML (type, onclick, x-on:click, etc.) se
    combina con las clases por defecto. El texto se pasa en el slot.
--}}
<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
