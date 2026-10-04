<?php

namespace App\Exports;

use App\Support\Texto;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Plantilla Excel para la importación masiva de estudiantes.
 *
 * Genera un archivo sencillo con las columnas básicas que espera la
 * importación (código, nombres, apellidos, documento, fecha de nacimiento y
 * sexo) y algunas filas de ejemplo con datos ficticios, para que el personal
 * del colegio sepa cómo llenarla. Las columnas se formatean como texto para
 * que Excel no borre los ceros iniciales de documentos como "0987654" ni
 * cambie el formato de las fechas.
 *
 * Se descarga desde ImportacionController, en la pantalla de importación de
 * estudiantes, usando la librería maatwebsite/excel.
 */
class PlantillaEstudiantesExport implements FromArray, WithMapping, WithStyles, WithTitle
{
    /**
     * Nombres de las columnas de la plantilla. Deben coincidir con los que
     * lee EstudiantesImport, por eso los dejamos en una constante pública.
     */
    public const ENCABEZADOS = [
        'codigo', 'nombres', 'apellidos', 'documento', 'fecha_nacimiento', 'sexo',
    ];

    /** Devuelve las filas de la plantilla: encabezados y ejemplos. */
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

    /**
     * Procesa cada fila antes de escribirla en el Excel. Aplicamos la
     * protección contra inyección de fórmulas también en la plantilla, para
     * ser coherentes con el resto de las exportaciones.
     */
    public function map($fila): array
    {
        return array_map(fn ($celda) => Texto::protegerFormula($celda), (array) $fila);
    }

    /** Nombre de la hoja dentro del archivo Excel. */
    public function title(): string
    {
        return 'Estudiantes';
    }

    /** Aplica el formato a la hoja: columnas como texto y encabezado en negrita. */
    public function styles(Worksheet $sheet): array
    {
        // Ponemos las columnas A a F (desde la fila 2 hasta la 1000) en formato
        // texto, para conservar los ceros iniciales del documento y evitar que
        // Excel convierta las fechas a su propio formato.
        foreach (range('A', 'F') as $col) {
            $sheet->getStyle("{$col}2:{$col}1000")->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        }

        // La fila 1 (encabezados) va en negrita.
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
