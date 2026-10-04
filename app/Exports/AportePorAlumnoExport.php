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
 * Exportación a Excel del reporte económico por alumno.
 *
 * Genera una hoja con el estado de cuenta resumido de cada alumno: lo
 * emitido, lo recaudado, lo vencido y el saldo del aporte mensual. Recibe
 * los datos de ReporteService::aportePorAlumno(), que a su vez usa
 * AporteService::estadoDeCuenta(), la misma fuente que la pantalla de
 * estado de cuenta y el PDF; por eso los totales coinciden al centavo.
 *
 * Como la obligación de pago es de cada alumno y no de la familia, el
 * reporte tiene una fila por alumno.
 *
 * Se descarga desde ReporteController.
 */
class AportePorAlumnoExport implements FromArray, WithMapping, WithStyles, WithTitle
{
    /**
     * Recibe el arreglo preparado por ReporteService::aportePorAlumno().
     *
     * @param array{gestion: mixed, filas: \Illuminate\Support\Collection, totales: array, hoy: string} $data
     */
    public function __construct(private array $data)
    {
    }

    /**
     * Arma las filas de la hoja: encabezado del reporte, una fila por alumno
     * y la fila de totales.
     */
    public function array(): array
    {
        $gestion = $this->data['gestion'];
        $t = $this->data['totales'];

        // Encabezado del reporte (filas 1 a 5), una fila vacía y en la fila 7
        // los títulos de las columnas.
        $filas = [
            ['REPORTE ECONÓMICO POR ALUMNO — APORTE MENSUAL'],
            ['Gestión', $gestion?->nombre ?? 'Todas'],
            ['Fecha de corte', \Illuminate\Support\Carbon::parse($this->data['hoy'])->format('d/m/Y')],
            ['Generado', now()->format('d/m/Y H:i')],
            ['Moneda', 'Bolivianos (Bs)'],
            [],
            ['Código', 'Alumno', 'Curso', 'Cuotas vencidas', 'Emitido (Bs)', 'Recaudado (Bs)', 'Vencido (Bs)', 'Saldo (Bs)'],
        ];

        // Una fila por alumno; los montos se convierten de centavos a número
        // decimal solo al escribir la celda.
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

        // Fila de totales, idéntica a la que muestran la pantalla y el PDF.
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

    /**
     * Protege las celdas de texto contra la inyección de fórmulas; los
     * números se dejan como números.
     */
    public function map($fila): array
    {
        return array_map(fn ($celda) => is_numeric($celda) ? $celda : Texto::protegerFormula($celda), (array) $fila);
    }

    /** Nombre de la hoja dentro del archivo Excel. */
    public function title(): string
    {
        return 'Aporte por alumno';
    }

    /** Aplica el formato visual de la hoja. */
    public function styles(Worksheet $sheet): array
    {
        // Las columnas E a H (montos en Bs) se muestran con separador de miles
        // y dos decimales.
        $sheet->getStyle('E8:H1000')->getNumberFormat()->setFormatCode('#,##0.00');
        // El código del alumno va como texto para conservar los ceros iniciales.
        $sheet->getStyle('A8:A1000')->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // Título destacado en la fila 1 y títulos de columna en negrita en la fila 7.
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            7 => ['font' => ['bold' => true]],
        ];
    }
}
