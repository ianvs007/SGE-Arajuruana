<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
    ];

    public static function getValor(string $clave, ?string $default = null): ?string
    {
        return Cache::remember("config.{$clave}", 60, function () use ($clave, $default) {
            $item = self::query()->where('clave', $clave)->first();

            return $item?->valor ?? $default;
        });
    }

    public static function setValor(string $clave, ?string $valor): void
    {
        self::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget("config.{$clave}");
    }
}
