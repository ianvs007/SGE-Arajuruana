<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Gestion (tabla `gestiones`).
 *
 * Representa una gestión escolar, es decir, el año lectivo (por ejemplo,
 * "Gestión 2026"). Es la entidad que organiza casi todo el sistema: los cursos,
 * las inscripciones, el calendario de excepciones y las cuotas de aporte se
 * registran siempre dentro de una gestión. Así podemos conservar el historial
 * de años anteriores sin mezclarlo con el año en curso.
 *
 * Se relaciona con Curso, Inscripcion, CalendarioExcepcion, AporteParametro y
 * CuotaAporte.
 */
class Gestion extends Model
{
    /** Indicamos la tabla porque el plural en español no lo deduce Laravel. */
    protected $table = 'gestiones';

    /**
     * Campos asignables de forma masiva:
     * - nombre: nombre visible de la gestión (por ejemplo, "Gestión 2026").
     * - anio: año numérico de la gestión.
     * - fecha_inicio / fecha_fin: inicio y cierre del año escolar.
     * - es_actual: marca la gestión con la que trabaja el sistema por defecto.
     * - activa: indica si la gestión sigue habilitada (las cerradas quedan como historial).
     * - observaciones: notas libres de la administración.
     */
    protected $fillable = [
        'nombre',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'es_actual',
        'activa',
        'observaciones',
    ];

    /**
     * Conversión de tipos: las fechas como objetos de fecha, el año como entero
     * y las banderas como booleanos para usarlas directamente en condiciones.
     */
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

    /**
     * Relación "uno a muchos" con los cursos.
     * En cada gestión se abren los cursos (paralelos) que funcionarán ese año.
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /**
     * Relación "uno a muchos" con las inscripciones.
     * Nos permite obtener todos los estudiantes inscritos en la gestión.
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * Relación "uno a muchos" con las excepciones del calendario
     * (feriados, jornadas sin clases y actividades internas de la gestión).
     */
    public function calendarioExcepciones(): HasMany
    {
        return $this->hasMany(CalendarioExcepcion::class);
    }

    /**
     * Relación "uno a uno" con los parámetros de aporte.
     * Cada gestión tiene su propia configuración de monto mensual, meses y
     * día de vencimiento, porque estos valores pueden cambiar de un año a otro.
     */
    public function aporteParametro(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AporteParametro::class);
    }

    /**
     * Relación "uno a muchos" con las cuotas de aporte emitidas en la gestión.
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaAporte::class);
    }

    /**
     * Obtiene la gestión vigente con la que trabaja el sistema.
     *
     * Primero buscamos la gestión marcada explícitamente como actual. Si nadie
     * la marcó, usamos como respaldo la gestión activa más reciente (la de año
     * mayor). Si no existe ninguna, devolvemos null y cada pantalla decide qué hacer.
     */
    public static function actual(): ?self
    {
        return self::query()->where('es_actual', true)->first()
            ?? self::query()->where('activa', true)->orderByDesc('anio')->first();
    }
}
