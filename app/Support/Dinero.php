<?php

namespace App\Support;

/**
 * Aritmética monetaria en CENTAVOS ENTEROS (§14).
 *
 * Regla confirmada: nunca operaciones de punto flotante para dinero.
 * Los montos entran como string/decimal de BD (p. ej. "40.00") y se operan
 * como enteros de centavos; la conversión a decimal de BD ocurre al persistir.
 */
final class Dinero
{
    /** Convierte un monto decimal (string|float|int) a centavos enteros. */
    public static function aCentavos(string|float|int|null $monto): int
    {
        if ($monto === null || $monto === '') {
            return 0;
        }

        // Redondeo half-up sobre el string decimal para evitar deriva binaria.
        return (int) round(((float) $monto) * 100);
    }

    /** Convierte centavos enteros a string decimal(10,2) para persistir. */
    public static function aDecimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    /** Formatea para pantalla: "Bs 40,00" (contexto boliviano, §2). */
    public static function formato(int $centavos): string
    {
        return 'Bs '.number_format($centavos / 100, 2, ',', '.');
    }

    /** Suma montos decimales vía centavos. */
    public static function sumar(array $montos): int
    {
        $total = 0;
        foreach ($montos as $monto) {
            $total += self::aCentavos($monto);
        }

        return $total;
    }
}
