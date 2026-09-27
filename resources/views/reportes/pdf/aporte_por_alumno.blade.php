{{--
    PDF: reporte económico POR ALUMNO (§14, §16).
    Mismos datos que la pantalla de estado de cuenta y el Excel:
    `ReporteService::aportePorAlumno()` → AporteService::estadoDeCuenta().
    La obligación es del alumno (§14). Totales idénticos al centavo (§16).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Aporte por alumno — {{ $data['gestion']?->nombre ?? 'Todas' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1e293b; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .institucion { font-size: 10.5px; color: #475569; margin-bottom: 10px; }
        .meta { margin-bottom: 10px; }
        .meta span { margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 3px 5px; text-align: left; }
        th { background: #f1f5f9; font-size: 8.5px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        tfoot td { background: #f8fafc; font-weight: bold; }
        .nota { margin-top: 10px; font-size: 8px; color: #64748b; }
        .vencido { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>Reporte económico por alumno — aporte mensual</h1>
    <div class="institucion">{{ config('institucion.nombre') }} — {{ config('institucion.distrito') }}</div>

    <div class="meta">
        <span><strong>Gestión:</strong> {{ $data['gestion']?->nombre ?? 'Todas' }}</span>
        <span><strong>Fecha de corte:</strong> {{ \Illuminate\Support\Carbon::parse($data['hoy'])->format('d/m/Y') }}</span>
        <span><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Alumno</th>
                <th>Curso</th>
                <th class="num">Vencidas</th>
                <th class="num">Emitido</th>
                <th class="num">Recaudado</th>
                <th class="num">Vencido</th>
                <th class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['filas'] as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ $fila['curso'] ?? '—' }}</td>
                    <td class="num">{{ $fila['cuotas_vencidas'] }}</td>
                    <td class="num">{{ \App\Support\Dinero::formato($fila['emitido']) }}</td>
                    <td class="num">{{ \App\Support\Dinero::formato($fila['pagado']) }}</td>
                    <td class="num vencido">{{ \App\Support\Dinero::formato($fila['vencido']) }}</td>
                    <td class="num">{{ \App\Support\Dinero::formato($fila['saldo']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTALES</td>
                <td class="num">{{ $data['totales']['cuotas_vencidas'] }}</td>
                <td class="num">{{ \App\Support\Dinero::formato($data['totales']['emitido']) }}</td>
                <td class="num">{{ \App\Support\Dinero::formato($data['totales']['pagado']) }}</td>
                <td class="num vencido">{{ \App\Support\Dinero::formato($data['totales']['vencido']) }}</td>
                <td class="num">{{ \App\Support\Dinero::formato($data['totales']['saldo']) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="nota">
        La obligación del aporte es del ALUMNO (§14): dos responsables del mismo alumno no
        duplican la cuota. Este reporte usa `AporteService::estadoDeCuenta()` — la misma
        fuente que la pantalla de estado de cuenta y el Excel; los totales coinciden al centavo.
    </p>
</body>
</html>
