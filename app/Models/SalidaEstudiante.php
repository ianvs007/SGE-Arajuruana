<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo SalidaEstudiante (tabla `salidas_estudiantes`).
 *
 * Registra las salidas autorizadas de un estudiante durante la jornada escolar
 * (por salud, emergencia familiar, trámites, etc.). Con este registro el colegio
 * sabe en todo momento quién autorizó la salida, a qué hora salió realmente el
 * alumno, quién lo recogió y si volvió al establecimiento.
 *
 * El flujo que acordamos con la institución es: autorizada, luego salida
 * efectiva y finalmente retornada (o bien cancelada). Algunas reglas importantes:
 * - El Director y Administración pueden autorizar la salida.
 * - Solo Administración registra la salida efectiva y el retorno.
 * - Que una salida esté autorizada no significa que el alumno ya se haya retirado.
 * - No se permite registrar otra salida abierta del mismo alumno sin cerrar la anterior.
 * - La hora de retorno no puede ser anterior a la de la salida efectiva.
 *
 * Se relaciona con Estudiante y con User (quién registró, autorizó, marcó la
 * salida y marcó el retorno).
 */
class SalidaEstudiante extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'salidas_estudiantes';

    /**
     * Campos asignables de forma masiva:
     * - estudiante_id / fecha: alumno y día de la salida.
     * - estado: etapa actual del flujo, ver la constante ESTADOS.
     * - motivo: razón de la salida, ver la constante MOTIVOS.
     * - autorizado_por / autorizado_en: quién autorizó y en qué momento.
     * - hora_salida / salida_en / salida_registrado_por: datos de la salida efectiva.
     * - responsable_retiro / documento_responsable: persona que recoge al alumno y su documento.
     * - verificacion_retiro: cómo se verificó la identidad de quien retira al alumno.
     * - hora_retorno / retorno_en / retorno_registrado_por: datos del retorno al colegio.
     * - observacion: notas adicionales.
     * - registrado_por: usuario que creó el registro.
     */
    protected $fillable = [
        'estudiante_id',
        'fecha',
        'estado',
        'motivo',
        'autorizado_por',
        'autorizado_en',
        'hora_salida',
        'salida_en',
        'salida_registrado_por',
        'responsable_retiro',
        'documento_responsable',
        'verificacion_retiro',
        'hora_retorno',
        'retorno_en',
        'retorno_registrado_por',
        'observacion',
        'registrado_por',
    ];

    /** Convertimos la fecha y las marcas de tiempo de cada etapa a objetos de fecha. */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'autorizado_en' => 'datetime',
            'salida_en' => 'datetime',
            'retorno_en' => 'datetime',
        ];
    }

    /** Estados del flujo de una salida, con un texto que aclara qué significa cada uno. */
    public const ESTADOS = [
        'autorizada' => 'Autorizada (aún no salió)',
        'salida_efectiva' => 'Salida efectiva registrada',
        'retornada' => 'Retornó al establecimiento',
        'cancelada' => 'Cancelada',
    ];

    /** Motivos predefinidos por los que se puede autorizar una salida. */
    public const MOTIVOS = [
        'salud' => 'Salud',
        'emergencia' => 'Emergencia familiar',
        'familiar' => 'Motivo familiar',
        'tramite' => 'Trámite institucional',
        'otro' => 'Otro',
    ];

    /**
     * Estados considerados "abiertos", es decir, que todavía no tienen una
     * resolución final (el alumno aún no salió o salió pero no ha retornado).
     */
    public const ESTADOS_ABIERTOS = ['autorizada', 'salida_efectiva'];

    /**
     * Relación "pertenece a" con el estudiante que sale.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación con el usuario que creó el registro de la salida.
     */
    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Relación con el usuario que autorizó la salida (Director o Administración).
     */
    public function autorizante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    /**
     * Relación con el usuario que registró la salida efectiva del alumno.
     */
    public function registroSalida(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salida_registrado_por');
    }

    /**
     * Relación con el usuario que registró el retorno del alumno al colegio.
     */
    public function registroRetorno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retorno_registrado_por');
    }

    /**
     * Devuelve el texto legible del estado actual.
     */
    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Devuelve el texto legible del motivo de la salida.
     */
    public function nombreMotivo(): string
    {
        return self::MOTIVOS[$this->motivo] ?? $this->motivo;
    }

    /**
     * Indica si la salida sigue abierta (autorizada o con salida efectiva sin retorno).
     */
    public function estaAbierta(): bool
    {
        return in_array($this->estado, self::ESTADOS_ABIERTOS, true);
    }

    /**
     * Comprueba si el alumno ya tiene una salida abierta en la misma fecha.
     *
     * Lo usamos antes de registrar o editar una salida para impedir duplicados.
     * El parámetro $excluirId sirve al editar: así no se cuenta la propia salida
     * que se está modificando.
     */
    public static function existeAbiertaPara(int $estudianteId, string $fecha, ?int $excluirId = null): bool
    {
        return static::query()
            ->where('estudiante_id', $estudianteId)
            ->whereDate('fecha', $fecha)
            ->whereIn('estado', self::ESTADOS_ABIERTOS)
            ->when($excluirId, fn ($q) => $q->whereKeyNot($excluirId))
            ->exists();
    }
}
