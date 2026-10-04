{{--
    Reporte imprimible: incidencias.
    Lista los casos disciplinarios con su fecha, estudiante, tipo, estado de seguimiento y un
    resumen de la descripción.
    Recibe del controlador $incidencias, ya filtradas según lo que el rol del usuario puede ver.
    Lo usa el personal con acceso a reportes.
--}}
{{-- Encabezado común de los reportes imprimibles, con el total de incidencias. --}}
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
        {{--
            Para el tipo se usa etiquetaPublica(), que devuelve un nombre apto para mostrar, y se
            marca "(confidencial)" cuando corresponde. La descripción se recorta a 120 caracteres
            para que la tabla impresa no quede demasiado larga.
        --}}
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
