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
