{{--
    Componente: x-secondary-link-button
    Tiene la apariencia de un botón secundario (fondo blanco con borde), pero en
    realidad es un enlace <a>. Lo creamos así para que el HTML sea válido, ya que
    no se debe poner un <button> dentro de un <a>. Lo usamos en botones como
    "Volver", "Cancelar" o "Ver detalle" que solo llevan a otra página.

    Atributos: href con la ruta de destino y cualquier otro atributo HTML
    (target, rel, etc.), que se combinan con las clases por defecto.
--}}
<a {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</a>
