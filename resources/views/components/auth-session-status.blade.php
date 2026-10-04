{{--
    Componente: x-auth-session-status
    Muestra en verde un mensaje de estado en las pantallas de autenticación, por
    ejemplo cuando se envió el enlace para restablecer la contraseña.

    Props: status, el texto del mensaje (normalmente session('status')).
--}}
@props(['status'])

{{-- Si no hay mensaje no se dibuja nada --}}
@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
        {{ $status }}
    </div>
@endif
