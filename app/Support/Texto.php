<?php

namespace App\Support;

/**
 * Utilidades de texto usadas en la importación y exportación de datos.
 *
 * Reúne dos tareas pequeñas pero importantes: proteger las celdas de los
 * archivos Excel que genera el sistema contra la llamada "inyección de
 * fórmulas", y normalizar textos para poder comparar nombres sin que
 * influyan las tildes, las mayúsculas o los espacios de más.
 *
 * La protección de fórmulas se usa en todas las exportaciones a Excel
 * (PlantillaEstudiantesExport, AsistenciaCursoExport, AportePorCursoExport y
 * AportePorAlumnoExport). La normalización está pensada para la detección de
 * duplicados durante la importación de estudiantes.
 */
final class Texto
{
    /**
     * Protege un valor contra la inyección de fórmulas en celdas exportadas.
     *
     * Programas como Excel interpretan como fórmula cualquier celda que
     * empiece con "=", "+", "-" o "@" (y también con tabulación o retorno de
     * carro). Si un usuario malintencionado escribiera, por ejemplo, un
     * nombre que empiece con "=", al abrir el archivo podría ejecutarse algo
     * no deseado. Para evitarlo anteponemos un apóstrofe, que hace que la
     * hoja de cálculo trate el contenido como texto plano.
     *
     * @return string El valor como texto, protegido si hacía falta.
     */
    public static function protegerFormula(mixed $valor): string
    {
        // Convertimos cualquier valor (incluido null) a texto.
        $texto = (string) ($valor ?? '');

        if ($texto === '') {
            return '';
        }

        // Revisamos solo el primer carácter, que es el que decide si la
        // hoja de cálculo lo interpreta como fórmula.
        $primer = $texto[0];
        if (in_array($primer, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$texto;
        }

        return $texto;
    }

    /**
     * Normaliza un texto para comparar posibles duplicados.
     *
     * Quita las tildes, pasa todo a minúsculas y reduce los espacios
     * repetidos a uno solo. De esta forma "José  Pérez" y "jose perez" se
     * consideran el mismo nombre.
     */
    public static function normalizar(?string $texto): string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return '';
        }

        // Transliteramos a ASCII para eliminar tildes y la ñ. Si iconv no
        // puede convertir algún carácter lo reemplaza por "?", así que
        // quitamos esos signos; si la conversión falla, usamos el texto original.
        $sinAcentos = strtr(iconv('UTF-8', 'ASCII//TRANSLIT', $texto) ?: $texto, ['?' => '']);

        // Pasamos a minúsculas y colapsamos cualquier secuencia de espacios.
        return preg_replace('/\s+/', ' ', mb_strtolower($sinAcentos, 'UTF-8')) ?? '';
    }
}
