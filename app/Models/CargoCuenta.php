<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargoCuenta extends Model
{
    protected $table = 'cargos_cuenta';

    protected $fillable = [
        'padre_id',
        'estudiante_id',
        'concepto',
        'monto',
        'fecha_emision',
        'fecha_vencimiento',
        'estado',
        'observacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'padre_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'cargo_id');
    }

    public function montoConfirmado(): float
    {
        return (float) $this->pagos()->where('estado', 'confirmado')->sum('monto');
    }

    public function montoPendiente(): float
    {
        return max(0, (float) $this->monto - $this->montoConfirmado());
    }
}
