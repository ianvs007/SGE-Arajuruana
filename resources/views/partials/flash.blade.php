{{--
    Parcial: mensajes flash.
    Muestra los avisos que el controlador deja en la sesión después de una acción, por ejemplo
    con redirect()->with('success', 'Registro guardado'). Estos mensajes duran solo una petición,
    por eso aparecen una vez y desaparecen al recargar. Se incluye con @include('partials.flash')
    en casi todas las pantallas del sistema, así los mensajes se ven igual en todos los módulos.
--}}
{{-- Mensaje de éxito (recuadro verde). --}}
@if (session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
@endif
{{-- Mensaje de error (recuadro rojo). --}}
@if (session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
@endif
