<?php

namespace App\Exports;

use App\Support\Texto;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación a Excel del reporte de asistencia de un curso.
 *
 * Genera una hoja con la asistencia de cada alumno de un curso, en un turno
 * y un rango de fechas, junto con los totales del curso. Recibe los datos ya
 * calculados por ReporteService::asistenciaCurso(), que es la misma fuente
 * que usan la pantalla de reporte de asistencia y el PDF; así garantizamos
 * que las cifras sean idénticas en los tres formatos.
 *
 * Igual que en el resto del sistema, el porcentaje se calcula sobre los días
 * con clases y los días "sin registro" se muestran aparte de las ausencias.
 *
 * Se descarga desde ReporteController.
 */
class AsistenciaCursoExport implements FromArray, WithMapping, WithStyles, WithTitle
{
    /**
     * Recibe el arreglo preparado por ReporteService::asistenciaCurso().
     *
     * @param array{curso: mixed, turno: string, desde: string, hasta: string, filas: array, totales: array} $data
     */
    public function __construct(private array $data)
    {
    }

    /**
     * Arma todas las filas de la hoja: un encabezado con los datos del
     * reporte, la tabla con una fila por alumno y, al final, los totales.
     */
    public function array(): array
    {
        // Traducimos el código del turno a su nombre legible usando la
        // constante TURNOS del modelo Curso.
        $curso = $this->data['curso'];
        $turnoLabel = $curso::TURNOS[$this->data['turno']] ?? $this->data['turno'];
        $t = $this->data['totales'];

        // Encabezado del reporte (filas 1 a 5), una fila vacía y en la fila 7
        // los títulos de las columnas.
        $filas = [
            ['REPORTE DE ASISTENCIA'],
            ['Curso', $curso->etiqueta()],
            ['Turno', $turnoLabel],
            ['Rango', $this->data['desde'].' al '.$this->data['hasta']],
            ['Generado', now()->format('d/m/Y H:i')],
            [],
            ['Código', 'Alumno', 'Días hábiles', 'Presente', 'Atrasado', 'Justificada', 'Ausente', 'Sin registro', 'Asistencia %'],
        ];

        // Una fila por alumno con sus conteos de asistencia.
        foreach ($this->data['filas'] as $fila) {
            $filas[] = [
                $fila['estudiante']?->codigo,
                $fila['estudiante']?->nombreCompleto(),
                $fila['dias_habiles'],
                $fila['conteo']['presente'],
                $fila['conteo']['atrasado'],
                $fila['conteo']['justificada'],
                $fila['conteo']['ausente'],
                $fila['sin_registro'],
                // El porcentaje se divide entre 100 para guardarlo como número
                // entre 0 y 1, que Excel muestra con formato de porcentaje. Si
                // no hubo días con clases se muestra un guion.
                $fila['porcentaje_asistencia'] !== null
                    ? $fila['porcentaje_asistencia'] / 100   // número entre 0 y 1 con formato de porcentaje
                    : '—',
            ];
        }

        // Fila de totales del curso, con las mismas cifras de la pantalla y del PDF.
        $filas[] = [];
        $filas[] = [
            'TOTALES DEL CURSO', $t['alumnos'].' alumnos',
            'Días hábiles: '.$t['dias_habiles'],
            $t['presente'], $t['atrasado'], $t['justificada'], $t['ausente'], $t['sin_registro'], '',
        ];

        return $filas;
    }

    /**
     * Protege cada celda contra la inyección de fórmulas. Los valores
     * numéricos se dejan tal cual para que Excel los siga tratando como
     * números y se puedan sumar.
     */
    public function map($fila): array
    {
        return array_map(fn ($celda) => is_numeric($celda) ? $celda : Texto::protegerFormula($celda), (array) $fila);
    }

    /** Nombre de la hoja dentro del archivo Excel. */
    public function title(): string
    {
        return 'Asistencia';
    }

    /** Aplica el formato visual de la hoja. */
    public function styles(Worksheet $sheet): array
    {
        // La columna I (porcentaje de asistencia) se muestra como porcentaje
        // con un decimal, desde la fila 8, donde empiezan los datos.
        $sheet->getStyle('I8:I1000')->getNumberFormat()->setFormatCode('0.0%');

        // Título más grande en la fila 1 y títulos de columna en negrita en la fila 7.
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            7 => ['font' => ['bold' => true]],
        ];
    }
}
