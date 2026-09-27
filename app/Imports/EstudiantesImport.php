<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lectura cruda de la hoja de estudiantes (§8).
 *
 * - Con heading row: la primera fila son los encabezados.
 * - Cédulas y teléfonos se fuerzan como texto desde la plantilla para no
 *   perder ceros iniciales (PhpSpreadsheet devuelve string si la celda es texto).
 * - La validación, detección de duplicados y previsualización se hacen en
 *   el controlador, no aquí: esta clase solo convierte la hoja en array.
 */
class EstudiantesImport implements ToArray, WithHeadingRow
{
    private array $filas = [];

    public function array(array $array): void
    {
        $this->filas = $array;
    }

    public function filas(): array
    {
        return $this->filas;
    }
}
