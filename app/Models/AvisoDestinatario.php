<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Destinatario materializado de un aviso (§13).
 *
 * Se crea al PUBLICAR el aviso, no al crearlo como borrador. La materialización
 * garantiza trazabilidad: si después cambia la inscripción del alumno o el
 * responsable vinculado, queda el registro exacto de a quién se le avisó.
 *
 * La confirmación de lectura es OPCIONAL y NO BLOQUEANTE (§13): `leido_en` y
 * `confirmado_en` solo registran el hecho; nunca impiden usar el sistema.
 */
class AvisoDestinatario extends Model
{
    protected $table = 'aviso_destinatarios';

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

    protected function casts(): array
    {
        return [
            'leido_en' => 'datetime',
            'confirmado_en' => 'datetime',
            'correo_enviado_en' => 'datetime',
        ];
    }

    /** Estados del intento de correo (§13: envío opcional, fallo no bloqueante). */
    public const CORREO_ESTADOS = [
        'enviado' => 'Enviado',
        'error' => 'Error de envío',
        'omitido' => 'Sin correo',
    ];

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(Aviso::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** ¿Ya se registró la lectura? (método explícito: `leido` como propiedad
     *  colisionaría con el acceso mágico de relaciones de Eloquent). */
    public function estaLeido(): bool
    {
        return $this->leido_en !== null;
    }

    public function estaConfirmado(): bool
    {
        return $this->confirmado_en !== null;
    }

    /** Registra la lectura (idempotente: no sobrescribe la primera lectura). */
    public function marcarLeido(): void
    {
        if ($this->leido_en === null) {
            $this->update(['leido_en' => now()]);
        }
    }

    /** Registra la confirmación opcional (idempotente). */
    public function marcarConfirmado(): void
    {
        if ($this->confirmado_en === null) {
            $this->update(['confirmado_en' => now(), 'leido_en' => $this->leido_en ?? now()]);
        }
    }

    public function nombreEstadoCorreo(): string
    {
        return self::CORREO_ESTADOS[$this->correo_estado] ?? ($this->correo_estado ?? '—');
    }
}
