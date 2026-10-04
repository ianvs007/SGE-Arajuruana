{{--
    Reporte imprimible: salidas de estudiantes.
    Lista las salidas anticipadas registradas (cuando un estudiante se retira antes de la hora
    de salida), con la fecha, la hora, el motivo y la persona que lo recogió.
    Recibe del controlador $salidas (colección con su estudiante).
    Lo usa el personal con acceso a reportes.
--}}
{{-- Encabezado común de los reportes imprimibles, con el total de salidas. --}}
@include('reportes._print_header', ['titulo' => 'Reporte de salidas', 'subtitulo' => 'Total: '.$salidas->count()])

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estudiante</th>
            <th>Motivo</th>
            <th>Responsable</th>
        </tr>
    </thead>
    <tbody>
        {{-- Una salida por fila; la hora se muestra sin segundos (HH:MM). --}}
        @forelse ($salidas as $salida)
            <tr>
                <td>{{ optional($salida->fecha)->format('d/m/Y') }}</td>
                <td>{{ \Illuminate\Support\Str::of($salida->hora_salida)->substr(0, 5) }}</td>
                <td>{{ $salida->estudiante?->nombreCompleto() }}</td>
                <td>{{ ucfirst($salida->motivo) }}</td>
                <td>{{ $salida->responsable_retiro }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

@include('reportes._print_footer')
