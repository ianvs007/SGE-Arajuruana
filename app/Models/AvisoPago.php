<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Aviso de pago del responsable familiar (§14, §20.13).
 *
 * El responsable informa el pago desde su cuenta con una NOTA ESCRITA, sin
 * adjuntar imagen de comprobante. Un aviso PENDIENTE no reduce deuda ni
 * acredita fondos ni genera comprobante: solo Administración lo valida.
 *
 * Estados: pendiente | validado | rechazado | anulado.
 * Un aviso validado origina exactamente un `Pago` (registro único, §14).
 */
class AvisoPago extends Model
{
    protected $table = 'avisos_pago';

    protected $fillable = [
        'referencia',
        'padre_id',
        'gestion_id',
        'monto_declarado',
        'nota',
        'estado',
        'informado_en',
        'revisado_por',
        'revisado_en',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'monto_declarado' => 'decimal:2',
            'informado_en' => 'datetime',
            'revisado_en' => 'datetime',
        ];
    }

    public const ESTADOS = [
        'pendiente' => 'Pendiente de validación',
        'validado' => 'Validado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /** El pago originado por este aviso (uno solo, §14). */
    public function pago(): HasOne
    {
        return $this->hasOne(Pago::class, 'aviso_id');
    }

    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto_declarado);
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public static function generarReferencia(): string
    {
        do {
            $referencia = 'AVI-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (self::where('referencia', $referencia)->exists());

        return $referencia;
    }
}
