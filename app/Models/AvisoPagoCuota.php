<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo AvisoPagoCuota (tabla `aviso_pago_cuotas`).
 *
 * Cada fila indica un mes (cuota de un hijo) que la familia declara estar
 * pagando en su aviso, y cuánto destina a ese mes. Puede ser el saldo completo
 * del mes o un abono parcial. Al validar el aviso, el operador cancela
 * exactamente estas cuotas con estos montos.
 */
class AvisoPagoCuota extends Model
{
    protected $table = 'aviso_pago_cuotas';

    protected $fillable = [
        'aviso_id',
        'cuota_id',
        'monto',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    /** Aviso al que pertenece esta línea. */
    public function aviso(): BelongsTo
    {
        return $this->belongsTo(AvisoPago::class, 'aviso_id');
    }

    /** Cuota (mes de un hijo) que se declara pagar. */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CuotaAporte::class, 'cuota_id');
    }

    /** Monto destinado a esta cuota, en centavos enteros. */
    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto);
    }
}
