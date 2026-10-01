{{--
    Comprobante interno PDF (§15).
    Reglas confirmadas:
    - Identificación única (comprobante_numero), sin valor fiscal.
    - SIN CUF, SIN apariencia de factura ni de documento tributario.
    - Si el pago está anulado, se marca claramente ANULADO (no desaparece).
    - Mismos totales que la pantalla (centavos → formato).
    - El QR impreso es SIMULADO y se marca como demostración (§20.16).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1e293b; margin: 24px; }
        .cabecera { border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 16px; }
        .cabecera table { width: 100%; border-collapse: collapse; }
        .cabecera h1 { font-size: 15px; margin: 0 0 4px; }
        .cabecera .institucion { font-size: 12px; font-weight: bold; }
        /* Logo institucional pequeño, arriba a la izquierda (30/09/2026). */
        .logo-institucional { width: 56px; height: 56px; }
        .cabecera .sub { font-size: 9px; color: #475569; margin-top: 2px; }
        .titulo-doc { text-align: center; margin: 14px 0; }
        .titulo-doc .tipo { font-size: 13px; font-weight: bold; letter-spacing: 1px; }
        .titulo-doc .numero { font-family: monospace; font-size: 12px; margin-top: 2px; }
        .aviso-fiscal { border: 1px solid #94a3b8; background: #f1f5f9; padding: 6px 10px; text-align: center;
                        font-size: 9px; color: #334155; margin-bottom: 14px; }
        .anulado { border: 2px solid #be123c; background: #fff1f2; color: #9f1239; padding: 8px 10px;
                   text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 14px; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.datos td { padding: 4px 6px; vertical-align: top; }
        table.datos td.etiqueta { color: #64748b; width: 22%; }
        table.detalle { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.detalle th { background: #f1f5f9; border-bottom: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; font-size: 10px; }
        table.detalle td { border-bottom: 1px solid #e2e8f0; padding: 5px 6px; }
        .derecha { text-align: right; }
        .total { font-weight: bold; font-size: 12px; }
        .pie { margin-top: 26px; border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 8.5px; color: #475569; }
        .firma { margin-top: 40px; width: 55%; border-top: 1px solid #334155; padding-top: 4px; font-size: 9px; text-align: center; }
        .qr-demo { margin-top: 10px; font-size: 8px; color: #92400e; }
    </style>
</head>
<body>
    <div class="cabecera">
        <table><tr>
            <td style="width: 64px; vertical-align: top;">@include('reportes._logo', ['base64' => true])</td>
            <td style="vertical-align: top; text-align: center;">
                <div class="institucion">UNIDAD EDUCATIVA ARAJURUANA FE Y ALEGRÍA</div>
                <h1>Sistema de Gestión Educativa</h1>
                <div class="sub">San Ignacio de Moxos, Beni — Bolivia · Documento de control interno</div>
            </td>
            <td style="width: 64px;"></td>
        </tr></table>
    </div>

    @if ($pago->estado === 'anulado')
        <div class="anulado">
            COMPROBANTE ANULADO{{ $pago->anulacion ? ' — '.$pago->anulacion->motivo : '' }}
        </div>
    @endif

    <div class="titulo-doc">
        <div class="tipo">COMPROBANTE INTERNO DE APORTE</div>
        <div class="numero">N.º {{ $pago->comprobante_numero }} · Ref. {{ $pago->referencia }}</div>
    </div>

    <div class="aviso-fiscal">
        COMPROBANTE INTERNO — NO VÁLIDO COMO FACTURA FISCAL.
        Documento de control interno de la Unidad Educativa; no constituye documento tributario
        ni acredita derecho a crédito fiscal.
    </div>

    <table class="datos">
        <tr>
            <td class="etiqueta">Fecha de validación</td>
            <td>{{ optional($pago->validado_en)->format('d/m/Y H:i') }}</td>
            <td class="etiqueta">Gestión</td>
            <td>{{ $pago->gestion?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Responsable familiar</td>
            <td>{{ $pago->padre?->name }}</td>
            <td class="etiqueta">Origen</td>
            <td>{{ $pago->aviso ? 'Aviso de pago '.$pago->aviso->referencia : 'Registro en ventanilla' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Validado por</td>
            <td>{{ $pago->confirmador?->name }}</td>
            <td class="etiqueta">Monto total</td>
            <td class="total">{{ \App\Support\Dinero::formato($pago->montoCentavos()) }}</td>
        </tr>
        @if ($pago->nota_responsable)
            <tr>
                <td class="etiqueta">Nota del responsable</td>
                <td colspan="3">{{ $pago->nota_responsable }}</td>
            </tr>
        @endif
        @if ($pago->observacion_operador)
            <tr>
                <td class="etiqueta">Observación</td>
                <td colspan="3">{{ $pago->observacion_operador }}</td>
            </tr>
        @endif
    </table>

    <table class="detalle">
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Periodo</th>
                <th>Concepto</th>
                <th class="derecha">Aplicado</th>
            </tr>
        </thead>
        <tbody>
            @php($suma = 0)
            @foreach ($pago->aplicaciones as $aplicacion)
                @php($suma += $aplicacion->montoCentavos())
                <tr>
                    <td>{{ $aplicacion->estudiante?->nombreCompleto() }}</td>
                    <td>{{ $aplicacion->cuota?->etiquetaPeriodo() }}</td>
                    <td>{{ $aplicacion->cuota?->concepto }}</td>
                    <td class="derecha">{{ \App\Support\Dinero::formato($aplicacion->montoCentavos()) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="total derecha">TOTAL APLICADO</td>
                <td class="total derecha">{{ \App\Support\Dinero::formato($suma) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="pie">
        Documento generado electrónicamente por el Sistema de Gestión Educativa el
        {{ now()->format('d/m/Y H:i') }} (hora de Bolivia).
        La obligación de aporte corresponde al alumno; este comprobante refleja la distribución
        registrada por Administración.
        <div class="qr-demo">
            Nota: el sistema incluye un código QR de DEMOSTRACIÓN para el proyecto de grado;
            su escaneo no acredita pago alguno.
        </div>
    </div>

    <div class="firma">Firma autorizada — Administración</div>
</body>
</html>
