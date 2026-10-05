<?php

namespace App\Models;

use App\Support\Dinero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Modelo AvisoPago (tabla `avisos_pago`).
 *
 * Representa el aviso con el que un responsable familiar informa, desde su
 * cuenta, que pagó el aporte con el QR del colegio. En el aviso marca los meses
 * (cuotas de sus hijos) que está pagando, indica la fecha del pago y sube el
 * comprobante que le dio su banco. Mientras el aviso esté pendiente no reduce
 * ninguna deuda, no acredita dinero ni genera comprobante interno: solo el
 * operador autorizado puede validarlo, después de revisar en la plataforma de
 * su banco que el dinero realmente ingresó.
 *
 * Estados: pendiente, validado, rechazado y anulado. Cuando un aviso se valida,
 * origina exactamente un Pago, que es el registro único del hecho económico.
 *
 * Se relaciona con User (el padre que avisa y el usuario que revisa), Gestion,
 * Pago y AvisoPagoCuota (los meses declarados).
 */
class AvisoPago extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'avisos_pago';

    /**
     * Campos asignables de forma masiva:
     * - referencia: código único del aviso (formato AVI-AAMMDD-XXXXXX).
     * - padre_id: responsable familiar que informa el pago.
     * - gestion_id: gestión a la que corresponde.
     * - monto_declarado: total pagado; es la suma de los meses marcados.
     * - fecha_pago: día en que la familia hizo el pago en su banco.
     * - nota: comentario opcional del responsable.
     * - comprobante_ruta / _nombre / _mime / _tamano: archivo del comprobante,
     *   guardado en el disco privado con un nombre al azar.
     * - comprobante_hash: huella SHA-256 del archivo, para detectar comprobantes repetidos.
     * - estado: pendiente, validado, rechazado o anulado.
     * - informado_en: momento en que se envió el aviso.
     * - revisado_por / revisado_en: usuario y momento de la revisión.
     * - motivo_rechazo: razón por la que se rechazó, si corresponde.
     */
    protected $fillable = [
        'referencia',
        'padre_id',
        'gestion_id',
        'monto_declarado',
        'fecha_pago',
        'nota',
        'comprobante_ruta',
        'comprobante_nombre',
        'comprobante_mime',
        'comprobante_tamano',
        'comprobante_hash',
        'estado',
        'informado_en',
        'revisado_por',
        'revisado_en',
        'motivo_rechazo',
    ];

    /** Guardamos el monto como decimal con dos cifras y las marcas de tiempo como fecha y hora. */
    protected function casts(): array
    {
        return [
            'monto_declarado' => 'decimal:2',
            'fecha_pago' => 'date',
            'informado_en' => 'datetime',
            'revisado_en' => 'datetime',
        ];
    }

    /** Tipos de archivo aceptados como comprobante. */
    public const COMPROBANTE_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    /** Tamaño máximo del comprobante, en kilobytes (5 MB). */
    public const COMPROBANTE_MAX_KB = 5120;

    /** Estados del aviso con un texto claro para el responsable familiar. */
    public const ESTADOS = [
        'pendiente' => 'Pendiente de validación',
        'validado' => 'Validado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
    ];

    /**
     * Relación con el responsable familiar que envió el aviso.
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    /**
     * Relación "pertenece a" con la gestión del aviso.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación con el usuario de Administración que revisó el aviso.
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /**
     * Relación "uno a uno" con el pago que se originó al validar este aviso.
     * Solo puede existir un pago por aviso, para no registrar dos veces el mismo dinero.
     */
    public function pago(): HasOne
    {
        return $this->hasOne(Pago::class, 'aviso_id');
    }

    /**
     * Relación "uno a muchos" con los meses (cuotas) que la familia declara pagar.
     */
    public function cuotasDeclaradas(): HasMany
    {
        return $this->hasMany(AvisoPagoCuota::class, 'aviso_id');
    }

    /** Indica si el aviso tiene un comprobante adjunto. */
    public function tieneComprobante(): bool
    {
        return $this->comprobante_ruta !== null;
    }

    /** Indica si el comprobante es una imagen (para mostrarlo directamente en pantalla). */
    public function comprobanteEsImagen(): bool
    {
        return in_array($this->comprobante_mime, ['image/jpeg', 'image/png'], true);
    }

    /**
     * Devuelve el monto declarado en centavos enteros, para operar sin errores de redondeo.
     */
    public function montoCentavos(): int
    {
        return Dinero::aCentavos($this->monto_declarado);
    }

    /**
     * Devuelve el texto legible del estado del aviso.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Indica si el aviso sigue esperando la revisión de Administración.
     */
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /**
     * Genera una referencia única para el aviso con el formato AVI-AAMMDD-XXXXXX.
     * Si por casualidad la referencia generada ya existe, se genera otra.
     */
    public static function generarReferencia(): string
    {
        do {
            $referencia = 'AVI-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (self::where('referencia', $referencia)->exists());

        return $referencia;
    }
}
