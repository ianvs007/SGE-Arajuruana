{{--
    Componente: x-primary-link-button
    Tiene la apariencia del botón principal (fondo oscuro), pero es un enlace <a>.
    Así evitamos anidar un <button> dentro de un <a>, que no es HTML válido.
    Lo usamos en acciones que llevan a otra pantalla, como "Nuevo estudiante"
    o "Registrar pago".

    Atributos: href con la ruta de destino y cualquier otro atributo HTML
    (target, rel, etc.), que se combinan con las clases por defecto.
--}}
<a {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</a>
