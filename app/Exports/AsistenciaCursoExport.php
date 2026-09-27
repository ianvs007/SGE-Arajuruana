<?php

namespace App\Exports;

use App\Support\Texto;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de asistencia por curso/turno/rango (§9, §16).
 *
 * Consume `ReporteService::asistenciaCurso()` — la MISMA fuente que la pantalla
 * `asistencias.reporte` y que el PDF: totales idénticos garantizados.
 * Denominador explícito de días hábiles; "sin registro" se reporta separado de
 * "ausente" (§9).
 */
class AsistenciaCursoExport implements FromArray, WithMapping, WithStyles, WithTitle
{
    /** @param array{curso: mixed, turno: string, desde: string, hasta: string, filas: array, totales: array} $data */
    public function __construct(private array $data)
    {
    }

    public function array(): array
    {
        $curso = $this->data['curso'];
        $turnoLabel = $curso::TURNOS[$this->data['turno']] ?? $this->data['turno'];
        $t = $this->data['totales'];

        $filas = [
            ['REPORTE DE ASISTENCIA'],
            ['Curso', $curso->etiqueta()],
            ['Turno', $turnoLabel],
            ['Rango', $this->data['desde'].' al '.$this->data['hasta']],
            ['Generado', now()->format('d/m/Y H:i')],
            [],
            ['Código', 'Alumno', 'Días hábiles', 'Presente', 'Atrasado', 'Justificada', 'Ausente', 'Sin registro', 'Asistencia %'],
        ];

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
                $fila['porcentaje_asistencia'] !== null
                    ? $fila['porcentaje_asistencia'] / 100   // celda numérica 0–1, formato %
                    : '—',
            ];
        }

        // Totales idénticos a pantalla/PDF (§16).
        $filas[] = [];
        $filas[] = [
            'TOTALES DEL CURSO', $t['alumnos'].' alumnos',
            'Días hábiles: '.$t['dias_habiles'],
            $t['presente'], $t['atrasado'], $t['justificada'], $t['ausente'], $t['sin_registro'], '',
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
        return 'Asistencia';
    }

    public function styles(Worksheet $sheet): array
    {
        // Columna I: porcentaje de asistencia como número con formato %.
        $sheet->getStyle('I8:I1000')->getNumberFormat()->setFormatCode('0.0%');

        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            7 => ['font' => ['bold' => true]],
        ];
    }
}
