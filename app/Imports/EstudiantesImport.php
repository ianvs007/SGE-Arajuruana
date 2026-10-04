<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lectura de la hoja Excel para la importación masiva de estudiantes.
 *
 * Esta clase solo se encarga de convertir el archivo Excel subido por el
 * usuario en un arreglo de PHP, usando la librería maatwebsite/excel. La
 * validación de los datos, la detección de duplicados y la vista previa
 * antes de guardar se hacen en ImportacionController, que es quien la usa.
 * Separamos así las responsabilidades: leer el archivo por un lado y
 * aplicar las reglas del sistema por otro.
 *
 * Detalles a tener en cuenta:
 * - Al implementar WithHeadingRow, la primera fila se toma como
 *   encabezados, y cada fila se devuelve como un arreglo asociativo
 *   (por ejemplo, $fila['nombres']).
 * - Los documentos de identidad y los teléfonos vienen como texto desde la
 *   plantilla para no perder los ceros iniciales (PhpSpreadsheet devuelve un
 *   string cuando la celda tiene formato de texto).
 */
class EstudiantesImport implements ToArray, WithHeadingRow
{
    /** Filas leídas de la hoja, guardadas para entregarlas al controlador. */
    private array $filas = [];

    /**
     * La librería llama a este método con el contenido de la hoja ya
     * convertido en arreglo; nosotros solo lo guardamos.
     */
    public function array(array $array): void
    {
        $this->filas = $array;
    }

    /** Devuelve las filas leídas para que el controlador las valide. */
    public function filas(): array
    {
        return $this->filas;
    }
}
