<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioExcepcion extends Model
{
    protected $table = 'calendario_excepciones';

    protected $fillable = [
        'gestion_id',
        'curso_id',
        'fecha',
        'tipo',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public const TIPO_SIN_CLASES = 'sin_clases';
    public const TIPO_FERIADO = 'feriado';
    public const TIPO_ACTIVIDAD_INTERNA = 'actividad_interna';

    public const TIPOS = [
        self::TIPO_SIN_CLASES => 'Jornada sin clases',
        self::TIPO_FERIADO => 'Feriado',
        self::TIPO_ACTIVIDAD_INTERNA => 'Actividad interna',
    ];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function nombreTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
