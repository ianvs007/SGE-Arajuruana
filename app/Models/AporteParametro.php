<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parámetros de aporte por gestión (§14).
 *
 * Configurables desde la aplicación: monto mensual, rango de meses y día de
 * vencimiento. Valores iniciales confirmados: Bs 40, febrero a noviembre, día 10.
 *
 * Regla confirmada (§14): un cambio de parámetros NO recalcula cuotas ya emitidas
 * ni reescribe deudas históricas silenciosamente — solo afecta cuotas futuras
 * que aún no se hayan generado.
 */
class AporteParametro extends Model
{
    protected $table = 'aporte_parametros';

    protected $fillable = [
        'gestion_id',
        'monto_mensual',
        'mes_inicio',
        'mes_fin',
        'dia_vencimiento',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'monto_mensual' => 'decimal:2',
            'mes_inicio' => 'integer',
            'mes_fin' => 'integer',
            'dia_vencimiento' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** Valores iniciales confirmados (§14); el colegio puede ajustarlos por gestión. */
    public const MONTO_INICIAL = 40.00;

    public const MES_INICIO = 2;   // febrero

    public const MES_FIN = 11;     // noviembre

    public const DIA_VENCIMIENTO = 10;

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaAporte::class, 'gestion_id', 'gestion_id');
    }

    /** Parámetros de la gestión actual (o los por defecto confirmados). */
    public static function deGestion(?Gestion $gestion = null): self
    {
        $gestion ??= Gestion::actual();

        if ($gestion) {
            $param = self::where('gestion_id', $gestion->id)->first();
            if ($param) {
                return $param;
            }
        }

        // Sin parámetros persistidos: instancia con los valores confirmados.
        return new self([
            'monto_mensual' => self::MONTO_INICIAL,
            'mes_inicio' => self::MES_INICIO,
            'mes_fin' => self::MES_FIN,
            'dia_vencimiento' => self::DIA_VENCIMIENTO,
            'activo' => true,
        ]);
    }

    /** Lista de meses [mes => nombre] según el rango configurado. */
    public function mesesDelRango(): array
    {
        $meses = [];
        for ($m = $this->mes_inicio; $m <= $this->mes_fin; $m++) {
            $meses[$m] = self::NOMBRES_MES[$m];
        }

        return $meses;
    }

    public const NOMBRES_MES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function mesNombre(int $mes): string
    {
        return self::NOMBRES_MES[$mes] ?? (string) $mes;
    }

    /**
     * Fecha de emisión de la cuota: primer día del mes.
     * (La obligación nace al iniciar el mes; el vencimiento se configura aparte.)
     */
    public function fechaEmisionPara(int $anio, int $mes): string
    {
        return sprintf('%04d-%02d-01', $anio, $mes);
    }

    /**
     * Fecha de vencimiento (§14): día configurado del mes (por defecto el 10).
     * Se ajusta al último día válido del mes si el día excede su longitud
     * (p. ej. día 31 en febrero → 28/29), sin fallar.
     */
    public function fechaVencimientoPara(int $anio, int $mes): string
    {
        $ultimoDia = (int) \Illuminate\Support\Carbon::create($anio, $mes, 1)->daysInMonth;
        $dia = min($this->dia_vencimiento, $ultimoDia);

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }
}
