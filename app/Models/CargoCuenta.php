<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo CargoCuenta (tabla `cargos_cuenta`).
 *
 * Representa un cargo económico asignado a un responsable familiar por un
 * alumno. Este modelo viene de una etapa anterior del proyecto; desde que se
 * rediseñó el módulo económico, el aporte mensual se maneja con CuotaAporte y
 * esta tabla se conserva para otros cargos extraordinarios que no son el aporte
 * mensual.
 *
 * Se relaciona con User (el padre al que se le asigna el cargo), Estudiante y Pago.
 */
class CargoCuenta extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'cargos_cuenta';

    /**
     * Campos asignables de forma masiva:
     * - padre_id: responsable familiar al que se le asigna el cargo.
     * - estudiante_id: alumno por el que se genera el cargo.
     * - concepto: descripción del cargo.
     * - monto: importe a pagar.
     * - fecha_emision / fecha_vencimiento: cuándo se emitió y hasta cuándo se debe pagar.
     * - estado: situación del cargo (pendiente, parcial, pagado o anulado).
     * - observacion: notas adicionales.
     * - creado_por: usuario que registró el cargo.
     */
    protected $fillable = [
        'padre_id',
        'estudiante_id',
        'concepto',
        'monto',
        'fecha_emision',
        'fecha_vencimiento',
        'estado',
        'observacion',
        'creado_por',
    ];

    /** Guardamos el monto como decimal con dos cifras y las fechas como objetos de fecha. */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    /**
     * Relación con el responsable familiar al que se le asignó el cargo.
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    /**
     * Relación "pertenece a" con el alumno por el que se genera el cargo.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación "uno a muchos" con los pagos realizados para este cargo.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'cargo_id');
    }

    /**
     * Suma el monto de los pagos confirmados de este cargo.
     * Solo contamos los pagos en estado "confirmado" (estado usado en el flujo
     * anterior), porque los pendientes o rechazados todavía no cubren la deuda.
     */
    public function montoConfirmado(): float
    {
        return (float) $this->pagos()->where('estado', 'confirmado')->sum('monto');
    }

    /**
     * Calcula cuánto falta pagar del cargo (monto menos lo confirmado).
     * Usamos max(0, ...) para que el resultado nunca sea negativo si se pagó de más.
     */
    public function montoPendiente(): float
    {
        return max(0, (float) $this->monto - $this->montoConfirmado());
    }
}
