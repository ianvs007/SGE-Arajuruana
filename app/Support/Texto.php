<?php

namespace App\Support;

/**
 * Utilidades de texto para importación/exportación (§8).
 */
final class Texto
{
    /**
     * Protege un valor contra inyección de fórmulas en celdas exportadas
     * (CSV/Excel): si empieza con =, +, -, @, tab o CR, se antepone un
     * apóstrofe para que las hojas de cálculo lo traten como texto.
     */
    public static function protegerFormula(mixed $valor): string
    {
        $texto = (string) ($valor ?? '');

        if ($texto === '') {
            return '';
        }

        $primer = $texto[0];
        if (in_array($primer, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$texto;
        }

        return $texto;
    }

    /**
     * Normaliza texto para comparación de duplicados: sin acentos,
     * minúsculas y espacios colapsados.
     */
    public static function normalizar(?string $texto): string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return '';
        }

        $sinAcentos = strtr(iconv('UTF-8', 'ASCII//TRANSLIT', $texto) ?: $texto, ['?' => '']);

        return preg_replace('/\s+/', ' ', mb_strtolower($sinAcentos, 'UTF-8')) ?? '';
    }
}
