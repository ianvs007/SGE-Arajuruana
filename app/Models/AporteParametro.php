<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo AporteParametro (tabla `aporte_parametros`).
 *
 * Guarda la configuración del aporte mensual de los padres de familia para una
 * gestión: el monto por mes, el rango de meses en que se cobra y el día de
 * vencimiento. Estos valores se pueden ajustar desde la aplicación; los valores
 * iniciales acordados con el colegio son Bs 40 al mes, de febrero a noviembre,
 * con vencimiento el día 10.
 *
 * Una regla importante: cambiar estos parámetros no recalcula las cuotas que ya
 * se emitieron ni modifica deudas pasadas sin que nadie lo note; el cambio solo
 * afecta a las cuotas futuras que todavía no se hayan generado.
 *
 * Se relaciona con Gestion y, a través de la gestión, con CuotaAporte.
 */
class AporteParametro extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'aporte_parametros';

    /**
     * Campos asignables de forma masiva:
     * - gestion_id: gestión a la que se aplican los parámetros.
     * - monto_mensual: monto que se cobra por alumno cada mes.
     * - mes_inicio / mes_fin: primer y último mes (1 a 12) en que se emiten cuotas.
     * - dia_vencimiento: día del mes en que vence la cuota.
     * - activo: indica si la configuración está vigente.
     */
    protected $fillable = [
        'gestion_id',
        'monto_mensual',
        'mes_inicio',
        'mes_fin',
        'dia_vencimiento',
        'activo',
    ];

    /**
     * Conversión de tipos. Guardamos el monto como decimal con dos cifras para
     * no perder precisión en los centavos; los meses y el día se manejan como enteros.
     */
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

    /** Valores iniciales acordados con el colegio; luego pueden ajustarse en cada gestión. */
    public const MONTO_INICIAL = 40.00;

    public const MES_INICIO = 2;   // febrero

    public const MES_FIN = 11;     // noviembre

    public const DIA_VENCIMIENTO = 10;

    /**
     * Relación "pertenece a" con la gestión configurada.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación con las cuotas emitidas en la misma gestión.
     * Como la cuota no guarda el id del parámetro, unimos ambas tablas por `gestion_id`.
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaAporte::class, 'gestion_id', 'gestion_id');
    }

    /**
     * Obtiene los parámetros de aporte de una gestión (por defecto, la actual).
     *
     * Si la gestión todavía no tiene parámetros guardados, devolvemos una
     * instancia en memoria con los valores iniciales acordados, para que el
     * sistema pueda trabajar sin haber configurado nada todavía.
     */
    public static function deGestion(?Gestion $gestion = null): self
    {
        $gestion ??= Gestion::actual();

        // Buscamos primero los parámetros guardados para esa gestión.
        if ($gestion) {
            $param = self::where('gestion_id', $gestion->id)->first();
            if ($param) {
                return $param;
            }
        }

        // Si no hay parámetros guardados, usamos una instancia (sin guardar) con los valores iniciales.
        return new self([
            'monto_mensual' => self::MONTO_INICIAL,
            'mes_inicio' => self::MES_INICIO,
            'mes_fin' => self::MES_FIN,
            'dia_vencimiento' => self::DIA_VENCIMIENTO,
            'activo' => true,
        ]);
    }

    /**
     * Devuelve la lista de meses en los que se cobra el aporte, con el formato
     * [número de mes => nombre], según el rango configurado.
     */
    public function mesesDelRango(): array
    {
        // Recorremos desde el mes de inicio hasta el mes final, ambos incluidos.
        $meses = [];
        for ($m = $this->mes_inicio; $m <= $this->mes_fin; $m++) {
            $meses[$m] = self::NOMBRES_MES[$m];
        }

        return $meses;
    }

    /** Nombres de los meses del año, indexados por su número. */
    public const NOMBRES_MES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Devuelve el nombre del mes indicado; si el número no es válido, lo devuelve como texto.
     */
    public function mesNombre(int $mes): string
    {
        return self::NOMBRES_MES[$mes] ?? (string) $mes;
    }

    /**
     * Calcula la fecha de emisión de una cuota: siempre el primer día del mes.
     * Consideramos que la obligación nace al empezar el mes; el vencimiento se
     * configura aparte con el día de vencimiento.
     *
     * @return string Fecha en formato Y-m-d.
     */
    public function fechaEmisionPara(int $anio, int $mes): string
    {
        return sprintf('%04d-%02d-01', $anio, $mes);
    }

    /**
     * Calcula la fecha de vencimiento de una cuota según el día configurado
     * (por defecto, el día 10 de cada mes).
     *
     * Si el día configurado no existe en ese mes (por ejemplo, el 31 en febrero),
     * se usa el último día del mes en lugar de provocar un error.
     *
     * @return string Fecha en formato Y-m-d.
     */
    public function fechaVencimientoPara(int $anio, int $mes): string
    {
        // Obtenemos cuántos días tiene el mes y limitamos el día de vencimiento a ese máximo.
        $ultimoDia = (int) \Illuminate\Support\Carbon::create($anio, $mes, 1)->daysInMonth;
        $dia = min($this->dia_vencimiento, $ultimoDia);

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }
}
