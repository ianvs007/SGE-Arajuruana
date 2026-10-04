<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo CuotaAporte (tabla `cuotas_aporte`).
 *
 * Representa la cuota mensual de aporte que corresponde a cada alumno. La
 * obligación de pago pertenece al alumno y no al padre: si una familia tiene
 * tres hijos en el colegio, se generan tres cuotas (Bs 120 en total si cada una
 * es de Bs 40). Del mismo modo, si el padre y la madre tienen cuentas separadas,
 * la cuota no se duplica, porque sigue siendo una sola por alumno.
 *
 * Estados de la cuota:
 * - pendiente: el saldo es igual al monto (todavía no se aplicó ningún pago).
 * - parcial: se pagó una parte, el saldo es mayor que cero pero menor que el monto.
 * - pagada: el saldo llegó a cero.
 * - exenta: Administración liberó al alumno de esta cuota; no genera deuda,
 *   pero queda registrado para mantener la trazabilidad.
 *
 * "Vencida" no es un estado guardado: se calcula comparando la fecha de
 * vencimiento con la fecha actual y revisando si todavía queda saldo.
 *
 * Se relaciona con Gestion, Estudiante, Inscripcion, PagoAplicacion (los pagos
 * aplicados a la cuota) y User (quién la creó).
 */
class CuotaAporte extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'cuotas_aporte';

    /**
     * Campos asignables de forma masiva:
     * - gestion_id / estudiante_id / inscripcion_id: a qué gestión, alumno e inscripción corresponde.
     * - anio / mes: periodo que cubre la cuota.
     * - monto: importe original de la cuota.
     * - saldo: lo que falta pagar; baja a medida que se aplican pagos.
     * - fecha_emision / fecha_vencimiento: cuándo nace la obligación y hasta cuándo se debe pagar.
     * - estado: pendiente, parcial, pagada o exenta.
     * - concepto / observacion: descripción de la cuota y notas adicionales.
     * - creado_por: usuario que generó la cuota.
     */
    protected $fillable = [
        'gestion_id',
        'estudiante_id',
        'inscripcion_id',
        'anio',
        'mes',
        'monto',
        'saldo',
        'fecha_emision',
        'fecha_vencimiento',
        'estado',
        'concepto',
        'observacion',
        'creado_por',
    ];

    /**
     * Conversión de tipos. El monto y el saldo se guardan como decimales con dos
     * cifras para no perder precisión en los centavos, y las fechas como objetos de fecha.
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'monto' => 'decimal:2',
            'saldo' => 'decimal:2',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    /** Estados posibles de una cuota con su texto para mostrar. */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'parcial' => 'Parcial',
        'pagada' => 'Pagada',
        'exenta' => 'Exenta',
    ];

    /** Nombres de los meses del año, indexados por su número. */
    public const NOMBRES_MES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Relación "pertenece a" con la gestión en la que se emitió la cuota.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación "pertenece a" con el alumno obligado a pagar la cuota.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación "pertenece a" con la inscripción del alumno en esa gestión.
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    /**
     * Relación "uno a muchos" con las aplicaciones de pago sobre esta cuota.
     * Una cuota puede pagarse en varias partes, por eso puede tener varias aplicaciones.
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class, 'cuota_id');
    }

    /**
     * Relación con el usuario que generó la cuota.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    // ---------- Cálculos en centavos enteros ----------
    // Para evitar errores de redondeo propios de los números decimales,
    // todas las operaciones con dinero se hacen en centavos enteros.

    /**
     * Devuelve el monto original de la cuota expresado en centavos.
     */
    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto);
    }

    /**
     * Devuelve el saldo pendiente expresado en centavos.
     */
    public function saldoCentavos(): int
    {
        return Dinero::aCentavos($this->saldo);
    }

    /**
     * Calcula cuánto se ha pagado ya de la cuota (monto menos saldo), en centavos.
     */
    public function pagadoCentavos(): int
    {
        return $this->montoCentavos() - $this->saldoCentavos();
    }

    /**
     * Indica si la cuota está vencida.
     *
     * No depende del estado guardado: una cuota está vencida cuando todavía
     * tiene saldo, no está exenta y su fecha de vencimiento ya pasó.
     *
     * @param  string|null  $hoy  Fecha de referencia en formato Y-m-d (por defecto, hoy).
     */
    public function estaVencida(?string $hoy = null): bool
    {
        $hoy ??= now()->toDateString();

        // Comparamos las fechas como texto Y-m-d, que mantiene el orden cronológico.
        return $this->saldoCentavos() > 0
            && $this->estado !== 'exenta'
            && $this->fecha_vencimiento->format('Y-m-d') < $hoy;
    }

    /**
     * Devuelve el texto legible del estado de la cuota.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Devuelve el nombre del mes que cubre la cuota.
     */
    public function nombreMes(): string
    {
        return self::NOMBRES_MES[$this->mes] ?? (string) $this->mes;
    }

    /**
     * Devuelve el periodo de la cuota en formato legible, por ejemplo "Marzo 2026".
     */
    public function etiquetaPeriodo(): string
    {
        return $this->nombreMes().' '.$this->anio;
    }

    /**
     * Recalcula el estado de la cuota a partir de su saldo actual.
     *
     * Se llama después de aplicar o revertir un pago para que el estado siempre
     * refleje el saldo real. Las cuotas exentas no se tocan, porque su estado lo
     * decide Administración y no depende del saldo. Este método no guarda en la
     * base de datos; solo actualiza los atributos del modelo.
     */
    public function sincronizarEstado(): void
    {
        // Las cuotas exentas conservan su estado sin importar el saldo.
        if ($this->estado === 'exenta') {
            return;
        }

        $saldo = $this->saldoCentavos();
        $monto = $this->montoCentavos();

        // Sin saldo la cuota queda pagada (y normalizamos el saldo a cero por si quedó negativo);
        // con un saldo menor al monto es un pago parcial; en otro caso sigue pendiente.
        if ($saldo <= 0) {
            $this->estado = 'pagada';
            $this->saldo = Dinero::aDecimal(0);
        } elseif ($saldo < $monto) {
            $this->estado = 'parcial';
        } else {
            $this->estado = 'pendiente';
        }
    }
}
