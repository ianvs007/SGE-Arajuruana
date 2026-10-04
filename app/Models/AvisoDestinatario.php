<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo AvisoDestinatario (tabla `aviso_destinatarios`).
 *
 * Representa a cada usuario que recibió un aviso. Estos registros se crean en
 * el momento de publicar el aviso, no mientras es un borrador. Guardar la lista
 * en ese momento nos da trazabilidad: si después cambia la inscripción del
 * alumno o el responsable vinculado, sigue quedando el registro exacto de a
 * quién se le avisó.
 *
 * La confirmación de lectura es opcional y no bloqueante: los campos `leido_en`
 * y `confirmado_en` solo registran el hecho, pero nunca impiden usar el sistema.
 *
 * Se relaciona con Aviso y con User (el destinatario).
 */
class AvisoDestinatario extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'aviso_destinatarios';

    /**
     * Campos asignables de forma masiva:
     * - aviso_id / user_id: aviso recibido y usuario que lo recibe.
     * - motivo: por qué este usuario es destinatario (por ejemplo, responsable de un alumno del curso).
     * - leido_en: momento en que abrió el aviso por primera vez.
     * - confirmado_en: momento en que confirmó la lectura (si se pidió confirmación).
     * - correo_enviado_en / correo_estado / correo_error: resultado del envío
     *   opcional por correo electrónico.
     */
    protected $fillable = [
        'aviso_id',
        'user_id',
        'motivo',
        'leido_en',
        'confirmado_en',
        'correo_enviado_en',
        'correo_estado',
        'correo_error',
    ];

    /** Convertimos las marcas de tiempo a fecha y hora. */
    protected function casts(): array
    {
        return [
            'leido_en' => 'datetime',
            'confirmado_en' => 'datetime',
            'correo_enviado_en' => 'datetime',
        ];
    }

    /**
     * Posibles resultados del envío por correo. El correo es un canal opcional:
     * si falla, el aviso sigue disponible dentro del sistema.
     */
    public const CORREO_ESTADOS = [
        'enviado' => 'Enviado',
        'error' => 'Error de envío',
        'omitido' => 'Sin correo',
    ];

    /**
     * Relación "pertenece a" con el aviso recibido.
     */
    public function aviso(): BelongsTo
    {
        return $this->belongsTo(Aviso::class);
    }

    /**
     * Relación con el usuario destinatario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Indica si el destinatario ya leyó el aviso.
     * Lo hicimos como método y no como propiedad `leido` porque ese nombre podría
     * confundirse con el acceso automático a relaciones que hace Eloquent.
     */
    public function estaLeido(): bool
    {
        return $this->leido_en !== null;
    }

    /**
     * Indica si el destinatario ya confirmó la lectura del aviso.
     */
    public function estaConfirmado(): bool
    {
        return $this->confirmado_en !== null;
    }

    /**
     * Registra que el destinatario leyó el aviso.
     * Solo guarda la fecha la primera vez, de modo que abrir el aviso de nuevo
     * no sobrescribe el momento de la primera lectura.
     */
    public function marcarLeido(): void
    {
        if ($this->leido_en === null) {
            $this->update(['leido_en' => now()]);
        }
    }

    /**
     * Registra la confirmación opcional de lectura.
     * Igual que la lectura, solo se guarda la primera vez. Si el usuario confirma
     * sin que se haya registrado antes la lectura, también la marcamos como leída.
     */
    public function marcarConfirmado(): void
    {
        if ($this->confirmado_en === null) {
            $this->update(['confirmado_en' => now(), 'leido_en' => $this->leido_en ?? now()]);
        }
    }

    /**
     * Devuelve el texto legible del estado del correo, o un guion si no hubo intento de envío.
     */
    public function nombreEstadoCorreo(): string
    {
        return self::CORREO_ESTADOS[$this->correo_estado] ?? ($this->correo_estado ?? '—');
    }
}
