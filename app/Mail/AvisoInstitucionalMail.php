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
 * Correo de aviso institucional (§13).
 *
 * - Variables EXPLÍCITAS y documentadas (sin secretos ni datos sensibles en el
 *   cuerpo): título, contenido, tipo, alcance, unidad educativa y enlace.
 * - El envío es OPCIONAL y su fallo no bloquea el aviso (lo gestiona
 *   `NotificacionService::enviarCorreos`).
 * - Configuração SMTP por `.env` (`MAIL_*`); nunca credenciales en el código.
 * - Si la confirmación de lectura es requerida, el correo aclara que es OPCIONAL
 *   y que el sistema se puede seguir usando sin confirmar (§13).
 */
class AvisoInstitucionalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Aviso $aviso,
        public User $destinatario,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto(),
        );
    }

    public function content(): Content
    {
        return new Content(
            // `markdown:` (no `view:`) para que se resuelvan los componentes
            // x-mail::* del tema por defecto del framework.
            markdown: 'mail.aviso_institucional',
            with: [
                // Variables documentadas del correo (§13):
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

    private function asunto(): string
    {
        return '['.config('institucion.sigla', 'UE Arajuruana').'] '.$this->aviso->titulo;
    }
}
