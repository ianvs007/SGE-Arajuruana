{{--
    Parcial: encabezado de los reportes imprimibles.
    Los reportes sencillos (estudiantes, pagos, salidas, incidencias, etc.) no usan el layout
    principal, sino una página HTML independiente y liviana pensada para imprimir desde el
    navegador. Este parcial abre esa página: define los estilos, los botones de imprimir y
    volver, el logo y el título. Se cierra con reportes._print_footer.
    Variables que debe definir la vista que lo incluye:
      - $titulo: título del reporte (obligatorio).
      - $subtitulo: texto adicional opcional, por ejemplo el rango de fechas.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    {{--
        Estilos propios del reporte, escritos a mano para que la página no dependa del CSS
        compilado del sistema. La regla @media print oculta los botones al imprimir.
    --}}
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .meta { color: #64748b; font-size: 13px; margin-bottom: 16px; }
        .actions { margin-bottom: 16px; }
        button, a.btn {
            background: #0f172a; color: #fff; border: 0; padding: 8px 14px;
            border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-block;
        }
        a.btn-secondary { background: #64748b; margin-left: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        /* Responsive: en pantallas estrechas la tabla mantiene un ancho
           mínimo legible y scrollea dentro de su contenedor (mismo patrón que el
           resto del sistema: overflow-x-auto), en vez de comprimir las columnas. */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap > table { min-width: 640px; }
        form.inline { display: inline-flex; gap: 8px; align-items: end; margin-bottom: 16px; }
        label { font-size: 13px; display: block; margin-bottom: 4px; }
        input[type="date"] { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; }
        /* Logo institucional pequeño, arriba a la izquierda (30/09/2026). */
        .cabecera-reporte { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 4px; }
        .logo-institucional { width: 56px; height: 56px; object-fit: contain; }
        @media print {
            .actions, .no-print { display: none !important; }
            body { margin: 0; }
            /* Al imprimir no hace falta scroll: la tabla vuelve a su ancho natural. */
            .table-wrap { overflow: visible; }
            .table-wrap > table { min-width: 0; }
        }
    </style>
</head>
<body>
    {{-- Botones de acción: imprimir con el diálogo del navegador o volver al menú de reportes. No salen en la hoja impresa. --}}
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a class="btn btn-secondary" href="{{ route('reportes.index') }}">Volver</a>
    </div>
    {{-- Cabecera del reporte: logo de la institución, título y fecha y hora de generación. --}}
    <div class="cabecera-reporte">
        @include('reportes._logo')
        <div>
            <h1>{{ $titulo }}</h1>
            <div class="meta">
                Sistema de Gestión Educativa · Generado el {{ now()->format('d/m/Y H:i') }}
                @isset($subtitulo)
                    · {{ $subtitulo }}
                @endisset
            </div>
        </div>
    </div>
