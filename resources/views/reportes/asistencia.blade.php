{{--
    Reporte imprimible: asistencia de un día.
    Lista todos los registros de asistencia de una fecha (estudiante, curso, estado y
    observación) en una página sencilla lista para imprimir.
    Recibe del controlador $fecha (fecha consultada en formato Y-m-d) y $asistencias
    (registros de esa fecha con su estudiante y curso).
    Lo usa el personal con acceso a reportes.
--}}
{{-- Encabezado común de los reportes imprimibles; el subtítulo muestra la fecha y el total de registros. --}}
@include('reportes._print_header', ['titulo' => 'Reporte de asistencia', 'subtitulo' => 'Fecha: '.(\Carbon\Carbon::parse($fecha)->format('d/m/Y')).' · Total: '.$asistencias->count()])

{{-- Selector de fecha. Tiene la clase no-print, así no aparece en la hoja impresa. --}}
<form method="GET" action="{{ route('reportes.asistencia') }}" class="inline no-print">
    <div>
        <label for="fecha">Fecha</label>
        <input id="fecha" type="date" name="fecha" value="{{ $fecha }}">
    </div>
    <button type="submit">Filtrar</button>
</form>

{{-- Tabla con un registro de asistencia por fila. --}}
<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Estudiante</th>
            <th>Curso</th>
            <th>Estado</th>
            <th>Observación</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($asistencias as $asistencia)
            <tr>
                <td>{{ $asistencia->estudiante?->nombreCompleto() }}</td>
                <td>{{ $asistencia->estudiante?->curso?->etiqueta() ?? '—' }}</td>
                <td>{{ ucfirst($asistencia->estado) }}</td>
                <td>{{ $asistencia->observacion ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Sin registros para la fecha seleccionada.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

{{-- Cierre de la página HTML del reporte. --}}
@include('reportes._print_footer')
