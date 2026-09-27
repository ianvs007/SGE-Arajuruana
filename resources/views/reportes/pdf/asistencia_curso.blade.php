{{--
    PDF: asistencia por curso/turno/rango (§9, §16).
    Mismos datos que la pantalla `asistencias.reporte` y que el Excel:
    `ReporteService::asistenciaCurso()` → EstadisticaAsistencia.
    Denominador EXPLÍCITO de días hábiles; "sin registro" ≠ "ausente" (§9).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Asistencia — {{ $data['curso']->etiqueta() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .institucion { font-size: 11px; color: #475569; margin-bottom: 10px; }
        .meta { margin-bottom: 10px; }
        .meta span { margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        tfoot td { background: #f8fafc; font-weight: bold; }
        .nota { margin-top: 10px; font-size: 8.5px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Reporte de asistencia</h1>
    <div class="institucion">{{ config('institucion.nombre') }} — {{ config('institucion.distrito') }}</div>

    <div class="meta">
        <span><strong>Curso:</strong> {{ $data['curso']->etiqueta() }}</span>
        <span><strong>Turno:</strong> {{ $data['curso']::TURNOS[$data['turno']] ?? $data['turno'] }}</span>
        <span><strong>Rango:</strong> {{ \Illuminate\Support\Carbon::parse($data['desde'])->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($data['hasta'])->format('d/m/Y') }}</span>
        <span><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Alumno</th>
                <th class="num">Días hábiles</th>
                <th class="num">Presente</th>
                <th class="num">Atrasado</th>
                <th class="num">Justificada</th>
                <th class="num">Ausente</th>
                <th class="num">Sin registro</th>
                <th class="num">Asistencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['filas'] as $fila)
                <tr>
                    <td>{{ $fila['estudiante']?->codigo }}</td>
                    <td>{{ $fila['estudiante']?->nombreCompleto() }}</td>
                    <td class="num">{{ $fila['dias_habiles'] }}</td>
                    <td class="num">{{ $fila['conteo']['presente'] }}</td>
                    <td class="num">{{ $fila['conteo']['atrasado'] }}</td>
                    <td class="num">{{ $fila['conteo']['justificada'] }}</td>
                    <td class="num">{{ $fila['conteo']['ausente'] }}</td>
                    <td class="num">{{ $fila['sin_registro'] }}</td>
                    <td class="num">{{ $fila['porcentaje_asistencia'] !== null ? number_format($fila['porcentaje_asistencia'], 1, ',', '.').'%' : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">TOTALES ({{ $data['totales']['alumnos'] }} alumnos)</td>
                <td class="num">{{ $data['totales']['dias_habiles'] }}</td>
                <td class="num">{{ $data['totales']['presente'] }}</td>
                <td class="num">{{ $data['totales']['atrasado'] }}</td>
                <td class="num">{{ $data['totales']['justificada'] }}</td>
                <td class="num">{{ $data['totales']['ausente'] }}</td>
                <td class="num">{{ $data['totales']['sin_registro'] }}</td>
                <td class="num"></td>
            </tr>
        </tfoot>
    </table>

    <p class="nota">
        Días hábiles = denominador explícito del rango según calendario del curso (§9):
        los días sin clases programadas no cuentan. «Sin registro» indica que había clases
        pero no se registró el estado; NO equivale a ausencia.
        Los porcentajes de asistencia se calculan sobre los días hábiles del rango.
    </p>
</body>
</html>
