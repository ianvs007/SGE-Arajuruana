<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salida autorizada (§10).
 *
 * Flujo confirmado: autorizada → salida_efectiva → retornada (o cancelada).
 * - Director y Administración autorizan.
 * - Solo Administración registra la salida efectiva y el retorno (§20.7).
 * - Autorizar NO significa que el alumno ya se retiró.
 * - No se permite duplicar una salida abierta del mismo alumno sin resolución.
 * - El retorno no puede ser anterior a la salida efectiva.
 */
class SalidaEstudiante extends Model
{
    protected $table = 'salidas_estudiantes';

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

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'autorizado_en' => 'datetime',
            'salida_en' => 'datetime',
            'retorno_en' => 'datetime',
        ];
    }

    /** Estados documentados (§10). */
    public const ESTADOS = [
        'autorizada' => 'Autorizada (aún no salió)',
        'salida_efectiva' => 'Salida efectiva registrada',
        'retornada' => 'Retornó al establecimiento',
        'cancelada' => 'Cancelada',
    ];

    public const MOTIVOS = [
        'salud' => 'Salud',
        'emergencia' => 'Emergencia familiar',
        'familiar' => 'Motivo familiar',
        'tramite' => 'Trámite institucional',
        'otro' => 'Otro',
    ];

    /** Estados "abiertos": sin resolución final. */
    public const ESTADOS_ABIERTOS = ['autorizada', 'salida_efectiva'];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function autorizante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    public function registroSalida(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salida_registrado_por');
    }

    public function registroRetorno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retorno_registrado_por');
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function nombreMotivo(): string
    {
        return self::MOTIVOS[$this->motivo] ?? $this->motivo;
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estado, self::ESTADOS_ABIERTOS, true);
    }

    /** ¿Existe una salida abierta del mismo alumno el mismo día? (§10: sin duplicados). */
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
