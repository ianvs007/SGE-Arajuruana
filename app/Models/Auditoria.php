<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Auditoria (tabla `auditoria`).
 *
 * Registra las acciones sensibles que realizan los usuarios en el sistema (por
 * ejemplo, validar un pago o asignar un rol): quién lo hizo, cuándo, qué acción
 * fue y sobre qué registro. Nos permite reconstruir lo que pasó si surge algún
 * problema. Por seguridad, aquí no se guardan contraseñas ni información confidencial.
 *
 * Se relaciona con User (el usuario que realizó la acción).
 */
class Auditoria extends Model
{
    /** Nombre de la tabla en la base de datos. */
    protected $table = 'auditoria';

    /**
     * Desactivamos los timestamps automáticos porque la tabla solo tiene
     * `created_at`: un registro de auditoría nunca se modifica, así que no
     * necesita `updated_at`.
     */
    public $timestamps = false;

    /**
     * Campos asignables de forma masiva:
     * - user_id: usuario que realizó la acción.
     * - accion: nombre de la acción (por ejemplo, "pagos.validar").
     * - subject_type / subject_id: tipo de modelo y id del registro afectado.
     * - datos: información adicional no sensible, guardada en JSON.
     * - ip: dirección IP desde la que se hizo la acción.
     * - created_at: momento de la acción.
     */
    protected $fillable = [
        'user_id',
        'accion',
        'subject_type',
        'subject_id',
        'datos',
        'ip',
        'created_at',
    ];

    /** Convertimos los datos JSON a arreglo de PHP y la fecha a objeto de fecha y hora. */
    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Relación "pertenece a" con el usuario que realizó la acción auditada.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
