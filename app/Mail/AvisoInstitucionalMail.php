<?php

namespace App\Mail;

use App\Models\Aviso;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo electrónico de un aviso institucional (comunicado).
 *
 * Arma el correo que recibe cada destinatario de un aviso publicado por el
 * colegio. Usa la plantilla Markdown
 * resources/views/mail/aviso_institucional.blade.php y se envía desde
 * NotificacionService::enviarCorreos(), que se encarga de que un fallo en el
 * envío no bloquee el aviso, ya que el correo es opcional.
 *
 * Criterios que seguimos:
 * - La plantilla recibe variables explícitas y documentadas (título,
 *   contenido, tipo, alcance, nombre del colegio y enlace), sin datos
 *   sensibles ni secretos en el cuerpo del correo.
 * - La configuración del servidor de correo (SMTP) se lee del archivo .env
 *   (variables MAIL_*); nunca se escriben credenciales en el código.
 * - Si el aviso pide confirmación de lectura, el correo aclara que es
 *   OPCIONAL y que se puede seguir usando el sistema sin confirmar.
 */
class AvisoInstitucionalMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Recibe el aviso y el usuario destinatario, para poder personalizar el
     * saludo del correo.
     */
    public function __construct(
        public Aviso $aviso,
        public User $destinatario,
    ) {
    }

    /** Define el asunto del correo. */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto(),
        );
    }

    /** Define el contenido del correo y las variables que recibe la plantilla. */
    public function content(): Content
    {
        return new Content(
            // Usamos `markdown:` en lugar de `view:` para poder aprovechar los
            // componentes x-mail::* del tema de correos que trae Laravel.
            markdown: 'mail.aviso_institucional',
            with: [
                // Variables que recibe la plantilla. El tipo y el alcance se
                // pasan ya traducidos a texto legible, y el enlace se arma con
                // la URL configurada de la aplicación para que funcione fuera
                // del navegador del usuario.
                'aviso' => $this->aviso,
                'destinatario' => $this->destinatario,
                'titulo' => $this->aviso->titulo,
                'contenido' => $this->aviso->contenido,
                'tipo' => $this->aviso->nombreTipo(),
                'alcance' => $this->aviso->descripcionAlcance(),
                'institucion' => config('app.name'),
                'unidadEducativa' => config('institucion.nombre', 'Unidad Educativa Arajuruana Fe y Alegría'),
                'requiereConfirmacion' => (bool) $this->aviso->requiere_confirmacion,
                'confirmarAntes' => $this->aviso->confirmar_antes,
                'enlaceSistema' => rtrim((string) config('app.url'), '/').route('avisos.show', $this->aviso, false),
            ],
        );
    }

    /**
     * Arma el asunto: la sigla del colegio entre corchetes seguida del
     * título del aviso, por ejemplo "[UE Arajuruana] Reunión de padres".
     */
    private function asunto(): string
    {
        return '['.config('institucion.sigla', 'UE Arajuruana').'] '.$this->aviso->titulo;
    }
}
