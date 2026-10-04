{{--
    Componente: x-dropdown-link
    Cada una de las opciones que aparecen dentro del menú desplegable (x-dropdown),
    por ejemplo "Perfil" o "Cerrar sesión" en el menú del usuario.

    Atributos: href y cualquier otro atributo HTML se pasan al enlace; el texto
    de la opción va en el slot.
--}}
<a {{ $attributes->merge(['class' => 'block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out']) }}>{{ $slot }}</a>
