{{--
    PDF: reporte económico por CURSO (§14, §16).
    Mismos datos que la pantalla de cuotas y el Excel:
    `ReporteService::aportePorCurso()` — centavos enteros vía Dinero.
    Las cuotas exentas no cuentan. Totales idénticos al centavo (§16).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Aporte por curso — {{ $data['gestion']?->nombre ?? 'Todas' }}</title>
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
        .vencido { color: #b91c1c; }
        /* Logo institucional pequeño, arriba a la izquierda (30/09/2026). */
        .cabecera-reporte { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .logo-institucional { width: 52px; height: 52px; }
    </style>
</head>
<body>
    <table class="cabecera-reporte"><tr>
        <td style="width: 60px; vertical-align: top;">@include('reportes._logo', ['base64' => true])</td>
        <td style="vertical-align: top;">
            <h1>Reporte económico por curso — aporte mensual</h1>
            <div class="institucion">{{ config('institucion.nombre') }} — {{ config('institucion.distrito') }}</div>
        </td>
    </tr></table>

    <div class="meta">
        <span><strong>Gestión:</strong> {{ $data['gestion']?->nombre ?? 'Todas' }}</span>
        <span><strong>Fecha de corte:</strong> {{ \Illuminate\Support\Carbon::parse($data['hoy'])->format('d/m/Y') }}</span>
        <span><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</span>
        <span><strong>Moneda:</strong> Bolivianos (Bs)</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Curso</th>
                <th class="num">Alumnos</th>
                <th class="num">Cuotas vencidas</th>
                <th class="num">Emitido</th>
                <th class="num">Recaudado</th>
                <th class="num">Vencido</th>
                <th class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['filas'] as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="num">{{ $fila['alumnos'] }}</td>
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
                <td>TOTALES</td>
                <td class="num">{{ $data['totales']['alumnos'] }}</td>
                <td class="num">{{ $data['totales']['cuotas_vencidas'] }}</td>
                <td class="num">{{ \App\Support\Dinero::formato($data['totales']['emitido']) }}</td>
                <td class="num">{{ \App\Support\Dinero::formato($data['totales']['pagado']) }}</td>
                <td class="num vencido">{{ \App\Support\Dinero::formato($data['totales']['vencido']) }}</td>
                <td class="num">{{ \App\Support\Dinero::formato($data['totales']['saldo']) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="nota">
        Emitido = suma de cuotas de la gestión (las exentas no cuentan). Recaudado = pagos
        aplicados y validados. Vencido = saldo de cuotas con fecha de vencimiento anterior
        a la fecha de corte. Saldo = deuda vigente (emitido − recaudado). Montos en centavos
        enteros (§14); este reporte usa la misma fuente que la pantalla y el Excel.
    </p>
</body>
</html>
