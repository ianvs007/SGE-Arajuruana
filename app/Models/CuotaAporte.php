<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuota de aporte mensual POR ALUMNO (§14, §20.10).
 *
 * La obligación pertenece al alumno: tres hijos generan tres cuotas (Bs 120 si
 * son Bs 40 c/u). Padre y madre con cuentas separadas NO duplican la cuota (§5).
 *
 * Estados:
 * - pendiente : saldo == monto (sin pagos aplicados)
 * - parcial   : 0 < saldo < monto
 * - pagada    : saldo == 0
 * - exenta    : Administración la exime (no genera deuda; trazable)
 *
 * "Vencida" NO es un estado: depende de fecha_vencimiento < hoy y saldo > 0 (§14).
 */
class CuotaAporte extends Model
{
    protected $table = 'cuotas_aporte';

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

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'parcial' => 'Parcial',
        'pagada' => 'Pagada',
        'exenta' => 'Exenta',
    ];

    public const NOMBRES_MES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class, 'cuota_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    // ---------- Aritmética en centavos enteros (§14) ----------

    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto);
    }

    public function saldoCentavos(): int
    {
        return Dinero::aCentavos($this->saldo);
    }

    public function pagadoCentavos(): int
    {
        return $this->montoCentavos() - $this->saldoCentavos();
    }

    /** ¿Está vencida? Depende de fecha y saldo, no del estado (§14). */
    public function estaVencida(?string $hoy = null): bool
    {
        $hoy ??= now()->toDateString();

        return $this->saldoCentavos() > 0
            && $this->estado !== 'exenta'
            && $this->fecha_vencimiento->format('Y-m-d') < $hoy;
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function nombreMes(): string
    {
        return self::NOMBRES_MES[$this->mes] ?? (string) $this->mes;
    }

    public function etiquetaPeriodo(): string
    {
        return $this->nombreMes().' '.$this->anio;
    }

    /**
     * Recalcula el estado a partir del saldo actual (en centavos).
     * No toca cuotas exentas.
     */
    public function sincronizarEstado(): void
    {
        if ($this->estado === 'exenta') {
            return;
        }

        $saldo = $this->saldoCentavos();
        $monto = $this->montoCentavos();

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
