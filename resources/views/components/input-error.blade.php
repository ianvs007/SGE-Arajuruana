{{--
    Componente: x-input-error
    Muestra en rojo los mensajes de error de validación de un campo. Se coloca
    debajo de cada campo en los formularios, pasándole los errores de ese campo
    que vienen en la variable $errors de Laravel.

    Props: messages, que puede ser un texto o un arreglo de mensajes.
--}}
@props(['messages'])

{{-- Solo se dibuja la lista si realmente hay errores para ese campo --}}
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
