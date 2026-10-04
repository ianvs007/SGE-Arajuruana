{{--
    Componente: x-text-input
    Campo de texto con el estilo común del sistema. Se reutiliza en los
    formularios de autenticación, perfil y otros módulos.

    Props: disabled (booleano) para deshabilitar el campo. Los demás atributos
    (name, type, value, required, etc.) se agregan directamente al <input>.
--}}
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}>
