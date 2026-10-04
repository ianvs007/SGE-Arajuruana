{{--
    Vista: Editar incidencia.
    Permite actualizar un caso ya registrado y, sobre todo, darle seguimiento: anotar las
    medidas aplicadas y cambiar su estado (abierta, en seguimiento o cerrada).
    Recibe del controlador $incidencia (el caso a editar) además de $estudiantes, $categorias
    y $estados que usa el formulario. La usa el personal que gestiona incidencias.
--}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar incidencia</h2></x-slot>
    <div class="py-8"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8"><div class="bg-white shadow-sm rounded-lg p-6">
        {{-- Se envía con @method('PUT') para que Laravel lo dirija a la ruta de actualización; al parcial le pasamos la incidencia para rellenar los campos. --}}
        <form method="POST" action="{{ route('incidencias.update', $incidencia) }}" class="space-y-4">@csrf @method('PUT') @include('incidencias._form', ['incidencia'=>$incidencia]) <x-primary-button>Actualizar</x-primary-button></form>
    </div></div></div>
</x-app-layout>
