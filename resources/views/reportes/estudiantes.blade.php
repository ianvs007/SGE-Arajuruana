{{--
    Reporte imprimible: estudiantes.
    Lista los estudiantes registrados con su código, nombre, documento, curso y estado.
    Recibe del controlador $estudiantes (colección con su curso).
    Lo usa el personal con acceso a reportes.
--}}
{{-- Encabezado común de los reportes imprimibles, con el total de estudiantes. --}}
@include('reportes._print_header', ['titulo' => 'Reporte de estudiantes', 'subtitulo' => 'Total: '.$estudiantes->count()])

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Estudiante</th>
            <th>Documento</th>
            <th>Curso</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        {{-- Un estudiante por fila; los datos que falten se reemplazan por un guion. --}}
        @forelse ($estudiantes as $estudiante)
            <tr>
                <td>{{ $estudiante->codigo }}</td>
                <td>{{ $estudiante->nombreCompleto() }}</td>
                <td>{{ $estudiante->documento ?? '—' }}</td>
                <td>{{ $estudiante->curso?->etiqueta() ?? '—' }}</td>
                <td>{{ ucfirst($estudiante->estado) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

@include('reportes._print_footer')
