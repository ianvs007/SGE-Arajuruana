<?php

namespace App\Mail;

use App\Models\Citacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo electrónico de citación a un padre o responsable.
 *
 * Arma el correo que se envía al responsable de un alumno cuando el colegio
 * lo cita (por ejemplo, para una reunión con el docente o la dirección).
 * Incluye la fecha, la hora, el alumno, el motivo y un enlace para ver la
 * citación dentro del sistema. Usa la plantilla Markdown
 * resources/views/mail/citacion.blade.php.
 *
 * Se envía desde CitacionController cuando el usuario elige mandar la
 * citación por correo.
 *
 * Privacidad: si la citación se originó en una incidencia CONFIDENCIAL, el
 * correo NO incluye la descripción detallada, solo el motivo general. Es el
 * mismo criterio que aplicamos en la pantalla y en el mensaje de WhatsApp.
 */
class CitacionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Recibe la citación y carga de una vez las relaciones que necesita la
     * plantilla (alumno, padre, incidencia y quién la generó), para no hacer
     * consultas adicionales al armar el correo.
     */
    public function __construct(
        public Citacion $citacion,
    ) {
        $this->citacion->loadMissing(['estudiante', 'padre', 'incidencia', 'generador']);
    }

    /**
     * Define el asunto del correo: la sigla del colegio entre corchetes y la
     * fecha de la citación, para que el destinatario la identifique rápido
     * en su bandeja de entrada.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('institucion.sigla', 'UE Arajuruana').'] Citación — '.
                optional($this->citacion->fecha)->format('d/m/Y'),
        );
    }

    /**
     * Define el contenido del correo y las variables que recibe la plantilla.
     */
    public function content(): Content
    {
        // Revisamos si la citación viene de una incidencia confidencial; si no
        // hay incidencia asociada, se considera no confidencial.
        $confidencial = (bool) ($this->citacion->incidencia?->confidencial ?? false);

        return new Content(
            // Usamos `markdown:` en lugar de `view:` para poder aprovechar los
            // componentes x-mail::* del tema de correos que trae Laravel.
            markdown: 'mail.citacion',
            with: [
                // Variables que recibe la plantilla. Si la incidencia es
                // confidencial, la descripción se envía vacía (null) para no
                // revelar su detalle. La hora se recorta a HH:MM y el enlace se
                // arma con la URL configurada de la aplicación.
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
