<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Auditoría acotada de acciones sensibles (§6).
 *
 * Registra: usuario, fecha, acción y registro afectado.
 * No almacena contraseñas ni contenido confidencial innecesario.
 */
class AuditoriaService
{
    /** Claves que nunca deben persistirse en los datos de auditoría. */
    private const CLAVES_SENSIBLES = [
        'password', 'password_confirmation', 'current_password',
        'remember_token', 'token', 'secret', 'api_key',
        'qr_payload', 'descripcion', 'observaciones', 'observacion',
        'nota', 'medida_accion', 'actuaciones',
    ];

    public static function registrar(
        string $accion,
        ?Model $subject = null,
        array $datos = []
    ): Auditoria {
        return Auditoria::create([
            'user_id' => Auth::id(),
            'accion' => $accion,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'datos' => self::sanear($datos),
            'ip' => Request::ip(),
        ]);
    }

    private static function sanear(array $datos): array
    {
        $limpio = [];
        foreach ($datos as $clave => $valor) {
            if (in_array(strtolower((string) $clave), self::CLAVES_SENSIBLES, true)) {
                continue;
            }
            // Solo valores escalares o arrays planos simples.
            if (is_scalar($valor) || is_null($valor)) {
                $limpio[$clave] = $valor;
            } elseif (is_array($valor)) {
                $limpio[$clave] = self::sanear($valor);
            }
        }

        return $limpio;
    }
}
