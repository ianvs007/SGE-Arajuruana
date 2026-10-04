{{--
    Componente: x-danger-button
    Botón rojo para acciones delicadas o irreversibles, como eliminar o anular un
    registro. El color llama la atención del usuario para que lo piense antes de
    hacer clic. Por defecto es de tipo "submit", es decir, envía el formulario.

    Atributos: cualquier atributo HTML se combina con las clases por defecto;
    el texto del botón se pasa en el slot.
--}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
