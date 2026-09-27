<?php

namespace App\Exports;

use App\Support\Texto;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Plantilla sencilla descargable con campos básicos y ejemplos ficticios (§8).
 * Las columnas de documento y fechas se formatean como texto para conservar
 * ceros iniciales.
 */
class PlantillaEstudiantesExport implements FromArray, WithMapping, WithStyles, WithTitle
{
    public const ENCABEZADOS = [
        'codigo', 'nombres', 'apellidos', 'documento', 'fecha_nacimiento', 'sexo',
    ];

    public function array(): array
    {
        return [
            self::ENCABEZADOS,
            // Ejemplos ficticios (no son personas reales)
            ['EST-2026-100', 'Juan Carlos', 'Mamani Quispe', '8123456', '2013-04-15', 'Masculino'],
            ['EST-2026-101', 'María Elena', 'Temo Cuasace', '0987654', '2014-09-02', 'Femenino'],
            ['EST-2026-102', 'Pedro', 'Nosa Roca', '', '2013-01-20', 'Masculino'],
        ];
    }

    /** Protege contra inyección de fórmulas también en la plantilla (§8). */
    public function map($fila): array
    {
        return array_map(fn ($celda) => Texto::protegerFormula($celda), (array) $fila);
    }

    public function title(): string
    {
        return 'Estudiantes';
    }

    public function styles(Worksheet $sheet): array
    {
        // Documento y fecha como texto (conserva ceros iniciales).
        foreach (range('A', 'F') as $col) {
            $sheet->getStyle("{$col}2:{$col}1000")->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
