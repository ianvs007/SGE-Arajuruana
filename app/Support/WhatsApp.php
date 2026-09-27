<?php

namespace App\Support;

use App\Models\Aviso;
use App\Models\Citacion;

/**
 * WhatsApp MANUAL (§13).
 *
 * Regla confirmada: la aplicación SOLO genera un enlace `wa.me` con el mensaje
 * precargado. NO hay API de WhatsApp, NO hay envío automático y NO se registra
 * "enviado por WhatsApp" (el envío lo decide y ejecuta el usuario desde su
 * teléfono). Por eso aquí no se persiste nada: solo se construyen URLs.
 *
 * Formato `wa.me`: https://wa.me/<número internacional sin +>?text=<urlencode>.
 * Sin número abre WhatsApp con el texto listo para elegir destinatario.
 */
final class WhatsApp
{
    /**
     * Normaliza un teléfono boliviano a formato internacional para wa.me.
     * Acepta 70000001, 70000001 Beni, +591 70000001, 59170000001, (591) 700-00001.
     * Devuelve null si no hay dígitos suficientes (no se inventa número).
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null || trim($telefono) === '') {
            return null;
        }

        // Conserva solo dígitos.
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';

        if ($digitos === '') {
            return null;
        }

        // Ya viene con prefijo 591.
        if (str_starts_with($digitos, '591') && strlen($digitos) >= 11) {
            return substr($digitos, 0, 12);
        }

        // Número nacional boliviano (8 dígitos: móvil 6/7, fijo con código).
        if (strlen($digitos) === 8) {
            return '591'.$digitos;
        }

        // 7 dígitos: móvil sin el primer dígito (p. ej. 0000001) — no se inventa.
        // Cualquier otro largo se devuelve tal cual si tiene al menos 8 dígitos.
        return strlen($digitos) >= 8 ? $digitos : null;
    }

    /**
     * Construye el enlace wa.me. `$telefono` null → enlace sin número
     * (el usuario elige el contacto en WhatsApp).
     */
    public static function enlace(?string $telefono, string $mensaje): string
    {
        $numero = self::normalizarTelefono($telefono);
        $texto = rawurlencode($mensaje);

        return $numero
            ? "https://wa.me/{$numero}?text={$texto}"
            : "https://wa.me/?text={$texto}";
    }

    /** Texto precargado para compartir un aviso institucional (§13). */
    public static function textoAviso(Aviso $aviso): string
    {
        $lineas = [
            '*'.$aviso->titulo.'*',
            'Unidad Educativa Arajuruana Fe y Alegría',
            '',
            $aviso->contenido,
        ];

        if ($aviso->requiere_confirmacion) {
            $lineas[] = '';
            $lineas[] = 'Confirmación de lectura OPCIONAL en el sistema'.
                ($aviso->confirmar_antes
                    ? ' (sugerida antes del '.$aviso->confirmar_antes->format('d/m/Y').')'
                    : '');
            $lineas[] = 'El sistema se puede seguir usando sin confirmar.';
        }

        $lineas[] = '';
        $lineas[] = 'Mensaje informativo generado por el sistema (envío manual).';

        return implode("\n", $lineas);
    }

    /**
     * Texto precargado para una citación (§12, §13).
     * §11: si hay incidencia confidencial asociada, el texto dirigido al
     * responsable NO reproduce su detalle.
     */
    public static function textoCitacion(Citacion $citacion): string
    {
        $citacion->loadMissing(['estudiante', 'padre']);

        $lineas = [
            '*Citación — Unidad Educativa Arajuruana Fe y Alegría*',
            '',
            'Estimado(a) '.($citacion->padre?->name ?? 'responsable').',',
            'Se le cita para el día '.optional($citacion->fecha)->format('d/m/Y').' a horas '.substr((string) $citacion->hora, 0, 5).'.',
            'Alumno(a): '.($citacion->estudiante?->nombreCompleto() ?? '—'),
            'Motivo: '.$citacion->motivo,
        ];

        if (! empty($citacion->descripcion)) {
            $lineas[] = '';
            $lineas[] = $citacion->descripcion;
        }

        $lineas[] = '';
        $lineas[] = 'Mensaje informativo generado por el sistema (envío manual).';

        return implode("\n", $lineas);
    }
}
