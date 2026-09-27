<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Pago validado: registro ÚNICO del hecho económico (§14).
 *
 * - Validar un aviso NO crea un segundo pago: el aviso validado origina
 *   exactamente un Pago (aviso_id).
 * - El monto validado se distribuye en `pago_aplicaciones` sobre cuotas
 *   (varios hijos y meses, §20.12).
 * - Después de validar se emite el comprobante interno (§15):
 *   `comprobante_numero`, sin valor fiscal.
 * - Estados: pendiente | en_revision | validado | rechazado | anulado.
 *   (Los estados viejos 'pendiente'/'en_revision'/'confirmado'/'rechazado' del
 *   flujo de la Etapa 2 se conservan en datos históricos; el flujo nuevo usa
 *   'validado' y 'anulado'.)
 */
class Pago extends Model
{
    protected $fillable = [
        'referencia',
        'comprobante_numero',
        'cargo_id',
        'aviso_id',
        'gestion_id',
        'padre_id',
        'monto',
        'monto_validado',
        'estado',
        'metodo',
        'qr_payload',
        'comprobante_nota',
        'nota_responsable',
        'whatsapp_destino',
        'solicitado_en',
        'confirmado_por',
        'confirmado_en',
        'validado_en',
        'observacion_operador',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_validado' => 'decimal:2',
            'solicitado_en' => 'datetime',
            'confirmado_en' => 'datetime',
            'validado_en' => 'datetime',
        ];
    }

    /** Estados del flujo nuevo (§14: comprensibles para avisos y pagos). */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_revision' => 'En revisión',
        'validado' => 'Validado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CargoCuenta::class, 'cargo_id');
    }

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(AvisoPago::class, 'aviso_id');
    }

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class);
    }

    public function anulacion(): HasOne
    {
        return $this->hasOne(PagoAnulacion::class);
    }

    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto_validado ?? $this->monto);
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function estaValidado(): bool
    {
        return $this->estado === 'validado';
    }

    /** ¿Puede procesarse (validar/anular)? Previene doble procesamiento (§20.14). */
    public function procesable(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_revision'], true);
    }

    public static function generarReferencia(): string
    {
        do {
            $referencia = 'SGE-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (self::where('referencia', $referencia)->exists());

        return $referencia;
    }

    /**
     * Identificación interna única del comprobante (§15), correlativa por año.
     *
     * `lockForUpdate` sobre el rango del año: dos validaciones concurrentes no
     * pueden emitir el mismo número (además existe la restricción única en BD,
     * que actuaría como último candado abortando la transacción).
     */
    public static function generarNumeroComprobante(): string
    {
        $anio = now()->format('Y');
        $ultimo = self::whereNotNull('comprobante_numero')
            ->where('comprobante_numero', 'like', "CI-{$anio}-%")
            ->orderByDesc('comprobante_numero')
            ->lockForUpdate()
            ->value('comprobante_numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('CI-%s-%05d', $anio, $siguiente);
    }
}
