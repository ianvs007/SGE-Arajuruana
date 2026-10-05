<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * Datos de la cuenta bancaria del colegio para el pago del aporte por QR.
 *
 * Administración los carga en Parámetros del aporte: la imagen del QR fijo que
 * entrega el banco, el nombre del banco, el titular y el número de cuenta. Se
 * guardan en la tabla `configuraciones` y se muestran a las familias en la
 * pantalla "Informar un pago". La imagen vive en el disco privado y se entrega
 * solo a usuarios que iniciaron sesión.
 */
final class DatosPago
{
    /** Claves de la tabla `configuraciones` para cada dato. */
    private const CLAVES = [
        'banco' => 'pago_banco',
        'titular' => 'pago_titular',
        'cuenta' => 'pago_cuenta',
        'qr_ruta' => 'pago_qr_ruta',
        'qr_mime' => 'pago_qr_mime',
    ];

    /**
     * Devuelve los datos de pago guardados.
     *
     * @return array{banco:?string, titular:?string, cuenta:?string, qr_ruta:?string, qr_mime:?string}
     */
    public static function obtener(): array
    {
        $datos = [];
        foreach (self::CLAVES as $campo => $clave) {
            $datos[$campo] = Configuracion::getValor($clave);
        }

        return $datos;
    }

    /** Indica si Administración ya subió la imagen del QR del colegio. */
    public static function tieneQr(): bool
    {
        return self::obtener()['qr_ruta'] !== null;
    }

    /**
     * Guarda uno o varios datos de pago.
     *
     * @param  array<string, ?string>  $valores  Campos de CLAVES con su nuevo valor.
     */
    public static function guardar(array $valores): void
    {
        foreach ($valores as $campo => $valor) {
            if (isset(self::CLAVES[$campo])) {
                Configuracion::setValor(self::CLAVES[$campo], $valor);
            }
        }
    }
}
