<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Categoría configurable de incidencias (§11).
 * Las categorías las administra Administración; no se inventan infracciones
 * ni artículos del reglamento en el código.
 */
class IncidenciaCategoria extends Model
{
    protected $table = 'incidencias_categorias';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }
}
