<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Modelo Pago (tabla `pagos`).
 *
 * Representa el registro único de un pago validado, es decir, el hecho
 * económico real. Lo diseñamos así para que el dinero nunca se cuente dos veces:
 * - Validar un aviso de pago no crea un segundo pago: cada aviso validado
 *   origina exactamente un Pago, enlazado mediante `aviso_id`.
 * - El monto validado se reparte en `pago_aplicaciones` entre las cuotas
 *   (puede cubrir varios hijos y varios meses).
 * - Una vez validado, se emite un comprobante interno con su número
 *   (`comprobante_numero`). Este comprobante no tiene valor fiscal.
 *
 * Estados: pendiente, en_revision, validado, rechazado y anulado. En los datos
 * históricos pueden aparecer estados del flujo anterior (por ejemplo,
 * "confirmado"); el flujo actual usa "validado" y "anulado".
 *
 * Se relaciona con CargoCuenta, AvisoPago, Gestion, User (padre que paga y
 * usuario que confirma), PagoAplicacion y PagoAnulacion.
 */
class Pago extends Model
{
    /**
     * Campos asignables de forma masiva:
     * - referencia: código único del pago para identificarlo fácilmente.
     * - comprobante_numero: número del comprobante interno (formato CI-AAAA-00001).
     * - cargo_id: cargo extraordinario asociado (opcional, del flujo anterior).
     * - aviso_id: aviso de pago que originó este pago.
     * - gestion_id: gestión a la que pertenece.
     * - padre_id: responsable familiar que realizó el pago.
     * - monto: monto informado; monto_validado: monto que Administración confirmó.
     * - estado: etapa del pago, ver la constante ESTADOS.
     * - metodo: forma de pago (ver la constante METODOS).
     * - operacion_bancaria: número de operación que el operador verificó en su banco
     *   (solo pagos por QR). operacion_bancaria_activa es la copia con restricción
     *   única mientras el pago está vigente; se vacía al anularlo.
     * - qr_payload / whatsapp_destino: datos del flujo antiguo de QR y WhatsApp.
     * - comprobante_nota / nota_responsable: notas sobre el comprobante y la nota del responsable.
     * - solicitado_en / confirmado_por / confirmado_en / validado_en: marcas de
     *   tiempo y usuario del proceso de revisión.
     * - verificado_en: momento en que el operador confirmó haber verificado el
     *   dinero (en su banco si fue por QR, o contado en mano si fue en efectivo).
     * - observacion_operador: comentario interno de quien procesó el pago.
     */
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
        'operacion_bancaria',
        'operacion_bancaria_activa',
        'qr_payload',
        'comprobante_nota',
        'nota_responsable',
        'whatsapp_destino',
        'solicitado_en',
        'confirmado_por',
        'confirmado_en',
        'validado_en',
        'verificado_en',
        'observacion_operador',
    ];

    /**
     * Conversión de tipos: los montos como decimales con dos cifras para no
     * perder centavos, y las marcas de tiempo como fecha y hora.
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_validado' => 'decimal:2',
            'solicitado_en' => 'datetime',
            'confirmado_en' => 'datetime',
            'validado_en' => 'datetime',
            'verificado_en' => 'datetime',
        ];
    }

    /**
     * Formas de pago. Los dos primeros son los del flujo actual; el resto
     * aparece solo en datos registrados antes del cambio.
     */
    public const METODOS = [
        'qr' => 'QR (transferencia bancaria)',
        'efectivo' => 'Efectivo en secretaría',
        'aviso_validado' => 'Aviso validado',
        'ventanilla' => 'Ventanilla',
        'qr_whatsapp' => 'QR y WhatsApp (flujo antiguo)',
    ];

    /** Devuelve el nombre legible de la forma de pago. */
    public function nombreMetodo(): string
    {
        return self::METODOS[$this->metodo] ?? (string) $this->metodo;
    }

    /**
     * Normaliza un número de operación bancaria para compararlo: sin espacios
     * al inicio o al final, sin espacios internos y en mayúsculas. Así "ab 123"
     * y "AB123" se reconocen como el mismo número.
     */
    public static function normalizarOperacion(?string $operacion): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $operacion));
    }

    /** Estados del flujo actual, con nombres fáciles de entender tanto para avisos como para pagos. */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_revision' => 'En revisión',
        'validado' => 'Validado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
    ];

    /**
     * Relación con el cargo extraordinario al que corresponde el pago (puede ser nulo).
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CargoCuenta::class, 'cargo_id');
    }

    /**
     * Relación con el aviso de pago que dio origen a este pago.
     */
    public function aviso(): BelongsTo
    {
        return $this->belongsTo(AvisoPago::class, 'aviso_id');
    }

    /**
     * Relación "pertenece a" con la gestión del pago.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación con el responsable familiar (usuario) que hizo el pago.
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    /**
     * Relación con el usuario de Administración que confirmó el pago.
     */
    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    /**
     * Relación "uno a muchos" con las aplicaciones del pago sobre las cuotas.
     * Permite ver exactamente a qué alumnos y meses se destinó el dinero.
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class);
    }

    /**
     * Relación "uno a uno" con el registro de anulación, si el pago fue anulado.
     */
    public function anulacion(): HasOne
    {
        return $this->hasOne(PagoAnulacion::class);
    }

    /**
     * Devuelve el monto del pago en centavos enteros.
     * Usamos el monto validado si existe; si todavía no se validó, el monto informado.
     */
    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto_validado ?? $this->monto);
    }

    /**
     * Devuelve el texto legible del estado del pago.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Indica si el pago ya fue validado por Administración.
     */
    public function estaValidado(): bool
    {
        return $this->estado === 'validado';
    }

    /**
     * Indica si el pago todavía puede procesarse (validarlo o anularlo).
     * Solo los pagos pendientes o en revisión son procesables; así evitamos que
     * un mismo pago se procese dos veces.
     */
    public function procesable(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_revision'], true);
    }

    /**
     * Genera una referencia única para el pago con el formato SGE-AAMMDD-XXXXXX.
     * Combinamos la fecha con seis caracteres aleatorios y repetimos el proceso
     * en el caso poco probable de que la referencia ya exista.
     */
    public static function generarReferencia(): string
    {
        do {
            $referencia = 'SGE-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (self::where('referencia', $referencia)->exists());

        return $referencia;
    }

    /**
     * Genera el siguiente número de comprobante interno, correlativo por año
     * (formato CI-AAAA-00001).
     *
     * Usamos `lockForUpdate` sobre los comprobantes del año para que dos
     * validaciones simultáneas no obtengan el mismo número. Además, la columna
     * tiene una restricción única en la base de datos que actúa como última
     * protección: si aun así se repitiera, la transacción se cancelaría.
     * Por eso este método debe llamarse dentro de una transacción.
     */
    public static function generarNumeroComprobante(): string
    {
        // Buscamos el último número emitido en el año actual, bloqueando esas filas mientras dure la transacción.
        $anio = now()->format('Y');
        $ultimo = self::whereNotNull('comprobante_numero')
            ->where('comprobante_numero', 'like', "CI-{$anio}-%")
            ->orderByDesc('comprobante_numero')
            ->lockForUpdate()
            ->value('comprobante_numero');

        // Tomamos los últimos cinco dígitos y sumamos uno; si es el primero del año, empezamos en 1.
        $siguiente = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('CI-%s-%05d', $anio, $siguiente);
    }
}
