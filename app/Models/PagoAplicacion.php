<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PagoAplicacion (tabla `pago_aplicaciones`).
 *
 * Representa la distribución de un pago sobre una cuota concreta. Un mismo pago
 * puede repartirse entre varios hijos y varios meses; es Administración quien
 * decide cómo se distribuye y lo registra. Cada fila indica qué parte del pago
 * se aplicó a qué cuota.
 *
 * Las reglas de la distribución se validan en el servicio AporteService:
 * - cada aplicación debe ser un monto positivo,
 * - no puede superar el saldo pendiente de la cuota,
 * - la suma de todas las aplicaciones debe ser igual al monto validado del
 *   pago (si sobra dinero, la operación se bloquea, tal como se decidió en la
 *   primera etapa del proyecto).
 *
 * Se relaciona con Pago, CuotaAporte, Estudiante y User (quién aplicó el pago).
 */
class PagoAplicacion extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'pago_aplicaciones';

    /**
     * Campos asignables de forma masiva:
     * - pago_id: pago del que sale el dinero.
     * - cuota_id: cuota a la que se aplica.
     * - estudiante_id: alumno dueño de la cuota (se guarda para facilitar las consultas).
     * - monto_aplicado: cantidad del pago destinada a esta cuota.
     * - aplicado_en / aplicado_por: cuándo y quién registró la aplicación.
     */
    protected $fillable = [
        'pago_id',
        'cuota_id',
        'estudiante_id',
        'monto_aplicado',
        'aplicado_en',
        'aplicado_por',
    ];

    /** Guardamos el monto como decimal con dos cifras y la fecha de aplicación como fecha y hora. */
    protected function casts(): array
    {
        return [
            'monto_aplicado' => 'decimal:2',
            'aplicado_en' => 'datetime',
        ];
    }

    /**
     * Relación "pertenece a" con el pago que se está distribuyendo.
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * Relación "pertenece a" con la cuota que recibe el monto.
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CuotaAporte::class, 'cuota_id');
    }

    /**
     * Relación "pertenece a" con el alumno al que corresponde la cuota.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación con el usuario de Administración que registró la aplicación.
     */
    public function aplicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicado_por');
    }

    /**
     * Devuelve el monto aplicado expresado en centavos enteros, para operar sin
     * errores de redondeo.
     */
    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto_aplicado);
    }
}
