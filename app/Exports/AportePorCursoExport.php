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
 * Excel económico por CURSO (§14, §16): emitido, recaudado, vencido y saldo.
 *
 * Consume `ReporteService::aportePorCurso()` — la MISMA fuente que la pantalla
 * de cuotas y el PDF. Montos en CENTAVOS enteros convertidos a número decimal;
 * los totales del pie coinciden al centavo con la pantalla (§16).
 * Las cuotas exentas no se cuentan (regla canónica de `CuotaAporte`).
 */
class AportePorCursoExport implements FromArray, WithMapping, WithStyles, WithTitle
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
            ['REPORTE ECONÓMICO POR CURSO — APORTE MENSUAL'],
            ['Gestión', $gestion?->nombre ?? 'Todas'],
            ['Fecha de corte', \Illuminate\Support\Carbon::parse($this->data['hoy'])->format('d/m/Y')],
            ['Generado', now()->format('d/m/Y H:i')],
            ['Moneda', 'Bolivianos (Bs)'],
            [],
            ['Curso', 'Alumnos', 'Cuotas vencidas', 'Emitido (Bs)', 'Recaudado (Bs)', 'Vencido (Bs)', 'Saldo (Bs)'],
        ];

        foreach ($this->data['filas'] as $fila) {
            $filas[] = [
                $fila['nombre'],
                $fila['alumnos'],
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
            'TOTALES', $t['alumnos'], $t['cuotas_vencidas'],
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
        return 'Aporte por curso';
    }

    public function styles(Worksheet $sheet): array
    {
        // Columnas D–G: montos en Bs con dos decimales.
        $sheet->getStyle('D8:G1000')->getNumberFormat()->setFormatCode('#,##0.00');

        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            7 => ['font' => ['bold' => true]],
        ];
    }
}
