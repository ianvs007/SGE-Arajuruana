{{--
    Reporte imprimible: citaciones.
    Lista las citaciones a padres o responsables con su fecha, hora, estudiante, padre citado,
    motivo y estado, en una página lista para imprimir.
    Recibe del controlador $citaciones (colección con su estudiante y padre).
    Lo usa el personal con acceso a reportes.
--}}
{{-- Encabezado común de los reportes imprimibles, con el total de citaciones. --}}
@include('reportes._print_header', ['titulo' => 'Reporte de citaciones', 'subtitulo' => 'Total: '.$citaciones->count()])

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estudiante</th>
            <th>Padre</th>
            <th>Motivo</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        {{-- La hora se recorta a sus 5 primeros caracteres (HH:MM) para no mostrar los segundos. --}}
        @forelse ($citaciones as $citacion)
            <tr>
                <td>{{ optional($citacion->fecha)->format('d/m/Y') }}</td>
                <td>{{ \Illuminate\Support\Str::of($citacion->hora)->substr(0, 5) }}</td>
                <td>{{ $citacion->estudiante?->nombreCompleto() }}</td>
                <td>{{ $citacion->padre?->name ?? '—' }}</td>
                <td>{{ $citacion->motivo }}</td>
                <td>{{ str_replace('_', ' ', ucfirst($citacion->estado)) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

@include('reportes._print_footer')
