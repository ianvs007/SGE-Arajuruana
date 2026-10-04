<?php

namespace App\Support;

/**
 * Clase de apoyo para la aritmética monetaria del sistema.
 *
 * Todo el módulo económico (aportes, cuotas, pagos y reportes) trabaja con
 * montos en bolivianos. Para evitar los errores de redondeo típicos de los
 * números en punto flotante (por ejemplo, que 0.1 + 0.2 no dé exactamente
 * 0.3), decidimos operar siempre con CENTAVOS ENTEROS: los montos llegan de
 * la base de datos como texto decimal (p. ej. "40.00"), se convierten a
 * enteros (4000), se suman o restan como enteros, y solo al guardar se
 * vuelven a convertir a decimal.
 *
 * Se utiliza principalmente desde AporteService y ReporteService, desde los
 * controladores del módulo económico (AportePagoController,
 * AvisoPagoController, CuotaAporteController, DashboardController), desde
 * los modelos CuotaAporte, Pago, AvisoPago y PagoAplicacion, y en varias
 * vistas Blade para mostrar montos con el formato boliviano.
 */
final class Dinero
{
    /**
     * Convierte un monto decimal (texto, número o entero) a centavos enteros.
     *
     * Multiplicamos por 100 y redondeamos al entero más cercano, de modo que
     * "40.00" se convierte en 4000. Un monto vacío o nulo se considera cero
     * para no romper las sumas.
     *
     * @param  string|float|int|null  $monto  Monto en bolivianos.
     * @return int Monto equivalente en centavos.
     */
    public static function aCentavos(string|float|int|null $monto): int
    {
        // Si no hay monto, lo tratamos como cero centavos.
        if ($monto === null || $monto === '') {
            return 0;
        }

        // Redondeamos al centavo más cercano (mitad hacia arriba) para que una
        // pequeña imprecisión binaria, como 39.999999, no se convierta en 3999.
        return (int) round(((float) $monto) * 100);
    }

    /**
     * Convierte centavos enteros al formato decimal que guarda la base de
     * datos (columna decimal(10,2)), por ejemplo 4000 pasa a "40.00".
     *
     * Usamos punto como separador decimal y ningún separador de miles,
     * porque así lo espera el motor de base de datos.
     */
    public static function aDecimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    /**
     * Da formato a un monto para mostrarlo en pantalla o en los PDF,
     * siguiendo la costumbre boliviana: "Bs 1.250,00" (punto para miles y
     * coma para decimales).
     */
    public static function formato(int $centavos): string
    {
        return 'Bs '.number_format($centavos / 100, 2, ',', '.');
    }

    /**
     * Suma una lista de montos decimales pasando cada uno por centavos.
     *
     * Así el total se acumula siempre en enteros y no arrastra errores de
     * redondeo, sin importar cuántos montos se sumen.
     *
     * @param  array  $montos  Lista de montos en bolivianos.
     * @return int Total en centavos.
     */
    public static function sumar(array $montos): int
    {
        // Recorremos los montos convirtiendo cada uno a centavos antes de sumar.
        $total = 0;
        foreach ($montos as $monto) {
            $total += self::aCentavos($monto);
        }

        return $total;
    }
}
