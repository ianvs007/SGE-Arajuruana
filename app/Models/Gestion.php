<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gestion extends Model
{
    protected $table = 'gestiones';

    protected $fillable = [
        'nombre',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'es_actual',
        'activa',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'anio' => 'integer',
            'es_actual' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function calendarioExcepciones(): HasMany
    {
        return $this->hasMany(CalendarioExcepcion::class);
    }

    public function aporteParametro(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AporteParametro::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaAporte::class);
    }

    /** Gestión vigente actual (o null si no se definió). */
    public static function actual(): ?self
    {
        return self::query()->where('es_actual', true)->first()
            ?? self::query()->where('activa', true)->orderByDesc('anio')->first();
    }
}
