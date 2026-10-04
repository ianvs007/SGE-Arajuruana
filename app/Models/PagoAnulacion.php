<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PagoAnulacion (tabla `pago_anulaciones`).
 *
 * Guarda el registro de la anulación de un pago que ya había sido validado.
 * Decidimos no permitir que los pagos se borren ni que sus montos se modifiquen
 * libremente: anular un pago revierte sus aplicaciones (devolviendo el saldo a
 * las cuotas) y deja este registro, que no se modifica, con el monto original,
 * quién anuló, cuándo, por qué motivo y qué aplicaciones se revirtieron.
 *
 * Se relaciona con Pago y con User (quién realizó la anulación).
 */
class PagoAnulacion extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'pago_anulaciones';

    /**
     * Campos asignables de forma masiva:
     * - pago_id: pago que se anuló.
     * - anulado_por / anulado_en: usuario y momento de la anulación.
     * - motivo: explicación obligatoria de por qué se anuló.
     * - monto_original: monto que tenía el pago antes de anularse.
     * - aplicaciones_revertidas: detalle (en JSON) de las cuotas y montos que se devolvieron.
     */
    protected $fillable = [
        'pago_id',
        'anulado_por',
        'anulado_en',
        'motivo',
        'monto_original',
        'aplicaciones_revertidas',
    ];

    /**
     * Conversión de tipos. Las aplicaciones revertidas se guardan como JSON en la
     * base de datos y Laravel las convierte automáticamente a un arreglo de PHP.
     */
    protected function casts(): array
    {
        return [
            'anulado_en' => 'datetime',
            'monto_original' => 'decimal:2',
            'aplicaciones_revertidas' => 'array',
        ];
    }

    /**
     * Relación "pertenece a" con el pago anulado.
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * Relación con el usuario que realizó la anulación.
     */
    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
