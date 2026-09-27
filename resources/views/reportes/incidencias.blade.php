@include('reportes._print_header', ['titulo' => 'Reporte de incidencias', 'subtitulo' => 'Total: '.$incidencias->count()])

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Estudiante</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Descripción</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($incidencias as $incidencia)
            <tr>
                <td>{{ optional($incidencia->fecha)->format('d/m/Y') }}</td>
                <td>{{ $incidencia->estudiante?->nombreCompleto() }}</td>
                <td>{{ $incidencia->etiquetaPublica() }}{{ $incidencia->confidencial ? ' (confidencial)' : '' }}</td>
                <td>{{ str_replace('_', ' ', ucfirst($incidencia->estado_seguimiento)) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($incidencia->descripcion, 120) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

@include('reportes._print_footer')
