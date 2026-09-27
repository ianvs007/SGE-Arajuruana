<?php

namespace App\Exports;

use App\Services\ReporteService;
use App\Support\Texto;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel económico POR ALUMNO (§14, §16): estado de cuenta resumido.
 *
 * Consume `ReporteService::aportePorAlumno()`, que reutiliza
 * `AporteService::estadoDeCuenta()` — la MISMA fuente que la pantalla de estado
 * de cuenta y el PDF. Totales idénticos al centavo (§16).
 * La obligación es del alumno (§14): una fila por alumno, no por familia.
 */
class AportePorAlumnoExport implements FromArray, WithMapping, WithStyles, WithTitle
{
    /** @param array{gestion: mixed, filas: \Illuminate\Support\Collection, totales: array, hoy: string} $data */
    public function __construct(private array $data)
    {
    }

    public function array(): array
    {
        $gestion = $this->data['gestion'];
        $t = $this->data['totales'];

        $filas = [
            ['REPORTE ECONÓMICO POR ALUMNO — APORTE MENSUAL'],
            ['Gestión', $gestion?->nombre ?? 'Todas'],
            ['Fecha de corte', \Illuminate\Support\Carbon::parse($this->data['hoy'])->format('d/m/Y')],
            ['Generado', now()->format('d/m/Y H:i')],
            ['Moneda', 'Bolivianos (Bs)'],
            [],
            ['Código', 'Alumno', 'Curso', 'Cuotas vencidas', 'Emitido (Bs)', 'Recaudado (Bs)', 'Vencido (Bs)', 'Saldo (Bs)'],
        ];

        foreach ($this->data['filas'] as $fila) {
            $filas[] = [
                $fila['codigo'],
                $fila['nombre'],
                $fila['curso'] ?? '—',
                $fila['cuotas_vencidas'],
                ReporteService::aNumero($fila['emitido']),
                ReporteService::aNumero($fila['pagado']),
                ReporteService::aNumero($fila['vencido']),
                ReporteService::aNumero($fila['saldo']),
            ];
        }

        // Totales idénticos a pantalla/PDF (§16).
        $filas[] = [];
        $filas[] = [
            'TOTALES', '', '', $t['cuotas_vencidas'],
            ReporteService::aNumero($t['emitido']),
            ReporteService::aNumero($t['pagado']),
            ReporteService::aNumero($t['vencido']),
            ReporteService::aNumero($t['saldo']),
        ];

        return $filas;
    }

    /** Protege contra inyección de fórmulas (§8). */
    public function map($fila): array
    {
        return array_map(fn ($celda) => is_numeric($celda) ? $celda : Texto::protegerFormula($celda), (array) $fila);
    }

    public function title(): string
    {
        return 'Aporte por alumno';
    }

    public function styles(Worksheet $sheet): array
    {
        // Columnas E–H: montos en Bs con dos decimales.
        $sheet->getStyle('E8:H1000')->getNumberFormat()->setFormatCode('#,##0.00');
        // Código como texto (conserva ceros iniciales).
        $sheet->getStyle('A8:A1000')->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            7 => ['font' => ['bold' => true]],
        ];
    }
}
