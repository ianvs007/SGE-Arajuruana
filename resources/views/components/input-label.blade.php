{{--
    Componente: x-input-label
    Etiqueta (label) de un campo de formulario con el estilo del sistema.

    Props: value, el texto de la etiqueta. Si no se envía, se usa el contenido
    del slot. Atributos como "for" se pasan directamente a la etiqueta <label>.
--}}
@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700']) }}>
    {{ $value ?? $slot }}
</label>
