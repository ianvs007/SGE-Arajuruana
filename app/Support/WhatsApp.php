<?php

namespace App\Support;

use App\Models\Aviso;
use App\Models\Citacion;

/**
 * Generación de enlaces de WhatsApp para el envío MANUAL de mensajes.
 *
 * El colegio no cuenta con la API oficial de WhatsApp, así que decidimos que
 * el sistema solo arme un enlace `wa.me` con el mensaje ya escrito. Al
 * pulsarlo, se abre WhatsApp en el teléfono o computadora del usuario y es
 * él quien decide si lo envía. Por eso aquí no se guarda nada en la base de
 * datos ni se marca un mensaje como "enviado por WhatsApp": solo se
 * construyen direcciones web.
 *
 * El formato del enlace es https://wa.me/<número internacional sin +>?text=<mensaje codificado>.
 * Si no se indica número, WhatsApp se abre con el texto listo y el usuario
 * elige a quién enviarlo.
 *
 * Se utiliza desde NotificacionService (avisos institucionales), desde
 * CitacionController (citaciones a padres) y en la vista avisos/show.blade.php.
 */
final class WhatsApp
{
    /**
     * Normaliza un teléfono boliviano al formato internacional que pide wa.me.
     *
     * Acepta formas variadas como 70000001, "70000001 Beni", "+591 70000001",
     * 59170000001 o "(591) 700-00001". Si no hay dígitos suficientes
     * devolvemos null, porque preferimos no generar un enlace antes que
     * inventar un número equivocado.
     *
     * @return string|null El número con prefijo 591, o null si no es válido.
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null || trim($telefono) === '') {
            return null;
        }

        // Nos quedamos solo con los dígitos, descartando espacios, signos y texto.
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';

        if ($digitos === '') {
            return null;
        }

        // El número ya trae el código de país de Bolivia (591): lo recortamos
        // a 12 dígitos como máximo (591 + 8 dígitos nacionales + margen).
        if (str_starts_with($digitos, '591') && strlen($digitos) >= 11) {
            return substr($digitos, 0, 12);
        }

        // Número nacional boliviano de 8 dígitos (celular que empieza en 6 o 7,
        // o fijo con su código): le agregamos el prefijo 591.
        if (strlen($digitos) === 8) {
            return '591'.$digitos;
        }

        // Con 7 dígitos o menos el número está incompleto y no lo completamos
        // por nuestra cuenta. Cualquier otro largo de al menos 8 dígitos se
        // devuelve tal como vino.
        return strlen($digitos) >= 8 ? $digitos : null;
    }

    /**
     * Construye el enlace wa.me con el mensaje precargado.
     *
     * Si el teléfono es null o no es válido, se genera un enlace sin número
     * para que el usuario elija el contacto dentro de WhatsApp.
     */
    public static function enlace(?string $telefono, string $mensaje): string
    {
        $numero = self::normalizarTelefono($telefono);
        // Codificamos el mensaje para que los espacios, saltos de línea y
        // tildes viajen correctamente dentro de la URL.
        $texto = rawurlencode($mensaje);

        return $numero
            ? "https://wa.me/{$numero}?text={$texto}"
            : "https://wa.me/?text={$texto}";
    }

    /**
     * Arma el texto que se precarga al compartir un aviso institucional.
     *
     * Incluye el título en negrita (los asteriscos son el formato de
     * WhatsApp), el nombre del colegio y el contenido. Si el aviso pide
     * confirmación de lectura se aclara que es opcional.
     */
    public static function textoAviso(Aviso $aviso): string
    {
        $lineas = [
            '*'.$aviso->titulo.'*',
            'Unidad Educativa Arajuruana Fe y Alegría',
            '',
            $aviso->contenido,
        ];

        // Si el aviso requiere confirmación, lo indicamos dejando claro que
        // no es obligatoria y, si existe, la fecha sugerida para confirmar.
        if ($aviso->requiere_confirmacion) {
            $lineas[] = '';
            $lineas[] = 'Confirmación de lectura OPCIONAL en el sistema'.
                ($aviso->confirmar_antes
                    ? ' (sugerida antes del '.$aviso->confirmar_antes->format('d/m/Y').')'
                    : '');
            $lineas[] = 'El sistema se puede seguir usando sin confirmar.';
        }

        // Pie que aclara que el mensaje es informativo y se envía a mano.
        $lineas[] = '';
        $lineas[] = 'Mensaje informativo generado por el sistema (envío manual).';

        return implode("\n", $lineas);
    }

    /**
     * Arma el texto que se precarga para enviar una citación a un padre o
     * responsable.
     *
     * Incluye el nombre del responsable, la fecha y hora de la citación, el
     * alumno y el motivo. Si la citación está relacionada con una incidencia
     * confidencial, el texto NO reproduce el detalle de esa incidencia: solo
     * se usan los campos propios de la citación.
     */
    public static function textoCitacion(Citacion $citacion): string
    {
        // Cargamos el estudiante y el padre solo si aún no estaban cargados.
        $citacion->loadMissing(['estudiante', 'padre']);

        // La hora se recorta a HH:MM para que se lea de forma natural.
        $lineas = [
            '*Citación — Unidad Educativa Arajuruana Fe y Alegría*',
            '',
            'Estimado(a) '.($citacion->padre?->name ?? 'responsable').',',
            'Se le cita para el día '.optional($citacion->fecha)->format('d/m/Y').' a horas '.substr((string) $citacion->hora, 0, 5).'.',
            'Alumno(a): '.($citacion->estudiante?->nombreCompleto() ?? '—'),
            'Motivo: '.$citacion->motivo,
        ];

        // La descripción es opcional; solo se agrega si fue escrita.
        if (! empty($citacion->descripcion)) {
            $lineas[] = '';
            $lineas[] = $citacion->descripcion;
        }

        $lineas[] = '';
        $lineas[] = 'Mensaje informativo generado por el sistema (envío manual).';

        return implode("\n", $lineas);
    }
}
