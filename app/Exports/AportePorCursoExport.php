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
 * Exportación a Excel del reporte económico por curso.
 *
 * Genera una hoja con lo emitido, lo recaudado, lo vencido y el saldo del
 * aporte mensual de cada curso. Recibe los datos ya calculados por
 * ReporteService::aportePorCurso(), la misma fuente que usan la pantalla de
 * cuotas y el PDF. Los montos vienen en centavos enteros y solo se
 * convierten a número decimal al escribir la celda, por lo que los totales
 * del pie coinciden al centavo con los de la pantalla. Las cuotas exentas no
 * se cuentan.
 *
 * Se descarga desde ReporteController.
 */
class AportePorCursoExport implements FromArray, WithMapping, WithStyles, WithTitle
{
    /**
     * Recibe el arreglo preparado por ReporteService::aportePorCurso().
     *
     * @param array{gestion: mixed, filas: \Illuminate\Support\Collection, totales: array, hoy: string} $data
     */
    public function __construct(private array $data)
    {
    }

    /**
     * Arma las filas de la hoja: encabezado del reporte, una fila por curso
     * y la fila de totales.
     */
    public function array(): array
    {
        $gestion = $this->data['gestion'];
        $t = $this->data['totales'];

        // Encabezado con la gestión, la fecha de corte (la que se usó para
        // decidir qué cuotas están vencidas), la fecha de generación y la
        // moneda. En la fila 7 van los títulos de las columnas.
        $filas = [
            ['REPORTE ECONÓMICO POR CURSO — APORTE MENSUAL'],
            ['Gestión', $gestion?->nombre ?? 'Todas'],
            ['Fecha de corte', \Illuminate\Support\Carbon::parse($this->data['hoy'])->format('d/m/Y')],
            ['Generado', now()->format('d/m/Y H:i')],
            ['Moneda', 'Bolivianos (Bs)'],
            [],
            ['Curso', 'Alumnos', 'Cuotas vencidas', 'Emitido (Bs)', 'Recaudado (Bs)', 'Vencido (Bs)', 'Saldo (Bs)'],
        ];

        // Una fila por curso. Los montos pasan de centavos a número decimal
        // recién aquí, para que Excel pueda operar con ellos.
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

        // Fila de totales, idéntica a la que muestran la pantalla y el PDF.
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
        return 'Aporte por curso';
    }

    /** Aplica el formato visual de la hoja. */
    public function styles(Worksheet $sheet): array
    {
        // Las columnas D a G (montos en Bs) se muestran con separador de miles
        // y dos decimales.
        $sheet->getStyle('D8:G1000')->getNumberFormat()->setFormatCode('#,##0.00');

        // Título destacado en la fila 1 y títulos de columna en negrita en la fila 7.
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            7 => ['font' => ['bold' => true]],
        ];
    }
}
