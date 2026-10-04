<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo IncidenciaCategoria (tabla `incidencias_categorias`).
 *
 * Representa las categorías con las que se clasifican las incidencias. Estas
 * categorías las administra Administración desde el sistema; decidimos no escribir en el
 * código infracciones ni artículos del reglamento, para que el colegio pueda
 * adaptarlas a su propia normativa.
 *
 * Cada categoría puede agrupar muchas incidencias (Incidencia).
 */
class IncidenciaCategoria extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'incidencias_categorias';

    /**
     * Campos asignables:
     * - nombre: nombre de la categoría.
     * - descripcion: explicación de qué tipo de hechos abarca.
     * - activa: permite ocultar una categoría sin borrarla, para no perder el
     *   historial de las incidencias que ya la usan.
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'activa',
    ];

    /** Convertimos la bandera de categoría activa a booleano. */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }
}
