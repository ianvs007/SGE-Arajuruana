{{--
    Plantilla PDF: reporte de asistencia por curso, turno y rango de fechas.
    El controlador la convierte en PDF con dompdf. Contiene los mismos datos que la pantalla
    del reporte de asistencia y que el Excel, ya que todos se calculan con
    ReporteService::asistenciaCurso(), que usa la clase EstadisticaAsistencia.
    El porcentaje se calcula sobre los días hábiles del rango (un denominador explícito) y
    "sin registro" no se cuenta como ausencia.
    Recibe del controlador $data con: 'curso', 'turno', 'desde', 'hasta', 'filas' y 'totales'.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Asistencia — {{ $data['curso']->etiqueta() }}</title>
    {{-- Estilos básicos que dompdf puede interpretar; la fuente DejaVu Sans muestra correctamente las tildes. --}}
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
        /* Logo institucional pequeño, arriba a la izquierda (30/09/2026). */
        .cabecera-reporte { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .logo-institucional { width: 52px; height: 52px; }
    </style>
</head>
<body>
    {{-- Cabecera en tabla con el logo incrustado en base64, el título y los datos de la institución. --}}
    <table class="cabecera-reporte"><tr>
        <td style="width: 60px; vertical-align: top;">@include('reportes._logo', ['base64' => true])</td>
        <td style="vertical-align: top;">
            <h1>Reporte de asistencia</h1>
            <div class="institucion">{{ config('institucion.nombre') }} — {{ config('institucion.distrito') }}</div>
        </td>
    </tr></table>

    {{-- Datos del filtro: curso, turno (nombre legible tomado de Curso::TURNOS), rango de fechas y fecha de generación. --}}
    <div class="meta">
        <span><strong>Curso:</strong> {{ $data['curso']->etiqueta() }}</span>
        <span><strong>Turno:</strong> {{ $data['curso']::TURNOS[$data['turno']] ?? $data['turno'] }}</span>
        <span><strong>Rango:</strong> {{ \Illuminate\Support\Carbon::parse($data['desde'])->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($data['hasta'])->format('d/m/Y') }}</span>
        <span><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</span>
    </div>

    {{-- Tabla principal: conteo de cada estado de asistencia por alumno y su porcentaje. --}}
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

    {{-- Nota aclaratoria sobre cómo se interpretan los días hábiles y los días sin registro. --}}
    <p class="nota">
        Días hábiles = denominador explícito del rango según calendario del curso:
        los días sin clases programadas no cuentan. «Sin registro» indica que había clases
        pero no se registró el estado; NO equivale a ausencia.
        Los porcentajes de asistencia se calculan sobre los días hábiles del rango.
    </p>
</body>
</html>
