<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplicación (distribución) de un pago sobre una cuota (§14, §20.12).
 *
 * Un pago puede distribuirse entre varios hijos y varios meses; Administración
 * decide y registra la distribución. Reglas validadas en AporteService:
 * - cada aplicación es positiva,
 * - no supera el saldo de la cuota,
 * - la suma aplicada == monto validado (exceso bloqueado, decisión Etapa 1).
 */
class PagoAplicacion extends Model
{
    protected $table = 'pago_aplicaciones';

    protected $fillable = [
        'pago_id',
        'cuota_id',
        'estudiante_id',
        'monto_aplicado',
        'aplicado_en',
        'aplicado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto_aplicado' => 'decimal:2',
            'aplicado_en' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CuotaAporte::class, 'cuota_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function aplicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicado_por');
    }

    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto_aplicado);
    }
}
