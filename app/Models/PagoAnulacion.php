<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anulación trazable de un pago validado (§14).
 *
 * Sin borrado silencioso ni modificación libre de importes históricos: anular
 * revierte las aplicaciones (devuelve saldo a las cuotas) y conserva un registro
 * inmutable del pago original, quién anuló, cuándo, por qué y qué se revirtió.
 */
class PagoAnulacion extends Model
{
    protected $table = 'pago_anulaciones';

    protected $fillable = [
        'pago_id',
        'anulado_por',
        'anulado_en',
        'motivo',
        'monto_original',
        'aplicaciones_revertidas',
    ];

    protected function casts(): array
    {
        return [
            'anulado_en' => 'datetime',
            'monto_original' => 'decimal:2',
            'aplicaciones_revertidas' => 'array',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
