<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Bitácora de auditoría de las acciones sensibles del sistema.
 *
 * Cada vez que alguien realiza una operación importante (crear o inactivar
 * usuarios, validar o anular pagos, corregir asistencia, registrar
 * incidencias, generar respaldos, etc.) se guarda un registro con el usuario,
 * la fecha, la acción, el registro afectado y la dirección IP. Esto permite
 * saber después quién hizo qué y cuándo.
 *
 * Por privacidad, la bitácora NO guarda contraseñas ni textos confidenciales
 * innecesarios (como descripciones de incidencias o notas personales).
 *
 * Se utiliza en casi todos los controladores que modifican datos
 * (UserController, EstudianteController, InscripcionController,
 * AsistenciaController, IncidenciaController, CitacionController,
 * AvisoController, SalidaEstudianteController, CursoController,
 * CuotaAporteController, AvisoPagoController, RespaldoController, etc.) y
 * también desde los servicios AporteService, NotificacionService y
 * RespaldoService.
 */
class AuditoriaService
{
    /**
     * Claves que nunca deben guardarse en los datos de auditoría. Incluye
     * credenciales y también campos de texto libre que pueden contener
     * información personal o confidencial de los estudiantes.
     */
    private const CLAVES_SENSIBLES = [
        'password', 'password_confirmation', 'current_password',
        'remember_token', 'token', 'secret', 'api_key',
        'qr_payload', 'descripcion', 'observaciones', 'observacion',
        'nota', 'medida_accion', 'actuaciones',
    ];

    /**
     * Registra una acción en la bitácora de auditoría.
     *
     * El usuario se toma de la sesión actual y la IP de la petición. Si se
     * indica un modelo afectado, se guarda su clase y su ID para poder
     * ubicarlo después. Los datos adicionales pasan antes por sanear().
     *
     * @param  string  $accion  Nombre de la acción, por ejemplo "pagos.validar".
     * @param  Model|null  $subject  Registro afectado por la acción, si lo hay.
     * @param  array  $datos  Información adicional útil para entender la acción.
     * @return Auditoria El registro de auditoría creado.
     */
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

    /**
     * Limpia los datos antes de guardarlos en la auditoría.
     *
     * Descarta las claves sensibles (sin importar mayúsculas o minúsculas) y
     * solo conserva valores simples (texto, números, booleanos o null). Si
     * un valor es un arreglo, se limpia de la misma forma de manera
     * recursiva. Los objetos se descartan porque no aportan a la bitácora y
     * podrían arrastrar información que no queremos guardar.
     */
    private static function sanear(array $datos): array
    {
        $limpio = [];
        foreach ($datos as $clave => $valor) {
            // Saltamos cualquier clave considerada sensible.
            if (in_array(strtolower((string) $clave), self::CLAVES_SENSIBLES, true)) {
                continue;
            }
            // Solo valores escalares o arreglos simples, que se limpian
            // llamando nuevamente a este mismo método.
            if (is_scalar($valor) || is_null($valor)) {
                $limpio[$clave] = $valor;
            } elseif (is_array($valor)) {
                $limpio[$clave] = self::sanear($valor);
            }
        }

        return $limpio;
    }
}
