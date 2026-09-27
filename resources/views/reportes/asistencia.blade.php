@include('reportes._print_header', ['titulo' => 'Reporte de asistencia', 'subtitulo' => 'Fecha: '.(\Carbon\Carbon::parse($fecha)->format('d/m/Y')).' · Total: '.$asistencias->count()])

<form method="GET" action="{{ route('reportes.asistencia') }}" class="inline no-print">
    <div>
        <label for="fecha">Fecha</label>
        <input id="fecha" type="date" name="fecha" value="{{ $fecha }}">
    </div>
    <button type="submit">Filtrar</button>
</form>

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

@include('reportes._print_footer')
