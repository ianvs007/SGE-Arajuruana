<?php

namespace App\Mail;

use App\Models\Citacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de citación (§12, §13).
 *
 * §11: si la citación proviene de una incidencia CONFIDENCIAL, el correo dirigido
 * al responsable NO reproduce su detalle (solo el motivo general), igual que la
 * pantalla y el WhatsApp.
 */
class CitacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Citacion $citacion,
    ) {
        $this->citacion->loadMissing(['estudiante', 'padre', 'incidencia', 'generador']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('institucion.sigla', 'UE Arajuruana').'] Citación — '.
                optional($this->citacion->fecha)->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        $confidencial = (bool) ($this->citacion->incidencia?->confidencial ?? false);

        return new Content(
            // `markdown:` (no `view:`) para que se resuelvan los componentes
            // x-mail::* del tema por defecto del framework.
            markdown: 'mail.citacion',
            with: [
                // Variables documentadas (§13); el detalle de incidencia
                // confidencial NO se incluye (§11).
                'citacion' => $this->citacion,
                'alumno' => $this->citacion->estudiante?->nombreCompleto(),
                'destinatario' => $this->citacion->padre?->name,
                'fecha' => optional($this->citacion->fecha)->format('d/m/Y'),
                'hora' => substr((string) $this->citacion->hora, 0, 5),
                'motivo' => $this->citacion->motivo,
                'descripcion' => $confidencial ? null : $this->citacion->descripcion,
                'unidadEducativa' => config('institucion.nombre', 'Unidad Educativa Arajuruana Fe y Alegría'),
                'enlaceSistema' => rtrim((string) config('app.url'), '/').route('citaciones.show', $this->citacion, false),
            ],
        );
    }
}
