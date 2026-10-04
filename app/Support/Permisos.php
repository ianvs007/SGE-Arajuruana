<?php

namespace App\Support;

/**
 * Catálogo oficial de permisos y roles del sistema.
 *
 * Aquí definimos, en un solo lugar, todos los permisos que existen y qué
 * permisos recibe cada rol. El seeder RolePermissionSeeder lee estas
 * constantes para crear los roles y permisos en la base de datos mediante
 * spatie/laravel-permission, y el modelo User y el UserController usan los
 * rangos de rol para impedir que un usuario asigne un rol superior al suyo.
 *
 * Reglas generales que seguimos:
 * - El acceso se niega por defecto: ningún cargo obtiene acceso total solo
 *   por su nombre, sino por los permisos que tiene asignados.
 * - Las cuentas las crea Administración; no existe registro público.
 * - Director y Coordinadora también pueden administrar cuentas de usuario.
 * - El Docente trabaja solo con sus cursos asignados y puede publicar
 *   avisos (incluso generales), pero no ve datos personales ni cuentas de
 *   todo el colegio.
 * - El Responsable familiar solo consulta lo autorizado de sus representados.
 * - Las incidencias confidenciales solo las ve Administración.
 *
 * La matriz fue corregida el 30/09/2026 a pedido de la dirección del colegio:
 * - Administración y Director: acceso a todo el sistema.
 * - Coordinadora: cuentas de mensualidades, asistencia diaria, cobro y
 *   validación de pagos, comunicados, descarga de toda la información
 *   económica, verificación de citaciones y de salidas/llegadas.
 * - Docente: comunicados y citaciones a padres, revisión de incidencias
 *   disciplinarias (solo lectura y solo de sus cursos), asistencia diaria de
 *   sus cursos y salidas/llegadas de sus estudiantes.
 * - Responsable Familiar: estado de cuenta de sus hijos, aviso de pagos,
 *   comunicados, citaciones y asistencia de sus hijos.
 */
final class Permisos
{
    /** Lista completa de permisos que maneja el sistema, agrupados por módulo. */
    public const TODOS = [
        // Usuarios y configuración
        'usuarios.gestionar',
        'configuracion.gestionar',
        'auditoria.ver',
        'respaldos.gestionar',
        // Alumnos e inscripciones
        'estudiantes.gestionar',
        'estudiantes.ver',
        'inscripciones.gestionar',
        'importacion.gestionar',
        // Vida escolar
        'asistencia.gestionar',
        'asistencia.ver',
        'salidas.autorizar',
        'salidas.registrar',
        'salidas.ver',
        'incidencias.gestionar',
        'incidencias.ver',        // consulta de incidencias sin poder editarlas (agregado para el Docente el 30/09/2026)
        'incidencias.confidenciales',
        'citaciones.gestionar',
        'citaciones.ver',
        'avisos.gestionar',
        'avisos.ver',
        // Módulo económico antiguo de cargos y pagos; se conserva para no perder los datos históricos
        'cuentas.gestionar',
        'cuentas.ver',
        'pagos.gestionar',
        'pagos.confirmar',
        'pagos.ver',
        'pagos.informar',
        // Módulo económico de aportes. Usamos el prefijo `aporte.*` para no
        // confundirlo con `cuota.*`, que ya se usaba para la asistencia
        'aporte.parametros',      // configurar monto, meses y vencimiento por gestión
        'aporte.cuotas.gestionar', // generar cuotas y eximir (Administración)
        'aporte.cuotas.ver',       // ver cuotas y el estado económico institucional
        'aporte.avisos.gestionar', // validar o rechazar avisos de pago
        'aporte.avisos.informar',  // la familia informa un pago con una nota escrita
        'aporte.pagos.anular',     // anular un pago validado dejando registro del motivo
        'aporte.estado_cuenta',    // estado de cuenta (la familia solo ve el de sus representados)
        // Transversales
        'reportes.ver',
        'historial.ver',
    ];

    /**
     * Rango numérico de cada rol. Sirve como salvaguarda contra la escalada
     * de privilegios: un usuario solo puede asignar roles de rango menor al
     * suyo (por ejemplo, una Coordinadora no puede crear un Director).
     */
    public const RANGO_ROL = [
        'Administración' => 100,
        'Director' => 90,
        'Coordinadora' => 80,
        'Subdirector' => 70,
        'Docente' => 50,
        'Responsable Familiar' => 10,
    ];

    /** Matriz que indica qué permisos recibe cada rol. */
    public const POR_ROL = [
        'Administración' => [
            'usuarios.gestionar',
            'configuracion.gestionar',
            'auditoria.ver',
            'respaldos.gestionar',
            'estudiantes.gestionar',
            'estudiantes.ver',
            'inscripciones.gestionar',
            'importacion.gestionar',
            'asistencia.gestionar',
            'asistencia.ver',
            'salidas.autorizar',
            'salidas.registrar',
            'salidas.ver',
            'incidencias.gestionar',
            'incidencias.confidenciales',
            'citaciones.gestionar',
            'citaciones.ver',
            'avisos.gestionar',
            'avisos.ver',
            'cuentas.gestionar',
            'cuentas.ver',
            'pagos.gestionar',
            'pagos.confirmar',
            'pagos.ver',
            'aporte.parametros',
            'aporte.cuotas.gestionar',
            'aporte.cuotas.ver',
            'aporte.avisos.gestionar',
            'aporte.pagos.anular',
            'aporte.estado_cuenta',
            'reportes.ver',
            'historial.ver',
        ],
        // El Director tiene acceso a todo el sistema (decisión del colegio del 30/09/2026).
        'Director' => self::TODOS,
        'Coordinadora' => [
            'usuarios.gestionar',
            'estudiantes.ver',
            'asistencia.gestionar',   // verifica la asistencia diaria
            'asistencia.ver',
            'salidas.autorizar',      // valida salidas y llegadas de estudiantes
            'salidas.ver',
            'citaciones.gestionar',   // verifica citaciones a padres
            'citaciones.ver',
            'avisos.gestionar',       // realiza comunicados
            'avisos.ver',
            'cuentas.gestionar',      // cuentas de las mensualidades (módulo antiguo)
            'cuentas.ver',
            'pagos.gestionar',        // cobro de mensualidades (módulo antiguo)
            'pagos.confirmar',        // valida los pagos (módulo antiguo)
            'pagos.ver',
            'aporte.cuotas.ver',      // estado económico institucional y deudores
            'aporte.avisos.gestionar',// valida o rechaza avisos de pago
            'aporte.estado_cuenta',   // estados de cuenta y lista de pagos
            'reportes.ver',           // descarga toda la información económica
            'historial.ver',
        ],
        'Subdirector' => [
            'estudiantes.ver',
            'reportes.ver',
        ],
        'Docente' => [
            'estudiantes.ver',       // limitado a sus cursos asignados (lo controla la Policy)
            'asistencia.gestionar',  // registra y verifica la asistencia de sus cursos
            'asistencia.ver',
            'salidas.autorizar',     // valida salidas y llegadas de SUS estudiantes
            'salidas.ver',
            'incidencias.ver',       // revisa casos disciplinarios (solo lectura)
            'citaciones.gestionar',  // citaciones a padres, solo de alumnos de sus cursos
            'citaciones.ver',
            'avisos.gestionar',      // realiza comunicados, incluidos los generales
            'avisos.ver',
            'historial.ver',         // limitado a alumnos de sus cursos y sin datos confidenciales
        ],
        'Responsable Familiar' => [
            'estudiantes.ver', // solo sus representados (se valida registro por registro)
            'asistencia.ver',  // consulta la asistencia de SUS hijos (solo lectura)
            'salidas.ver',     // solo las de sus representados, que es la información autorizada
            'citaciones.ver',  // solo las dirigidas a él
            'avisos.ver',      // solo los que le corresponden
            'cuentas.ver',     // solo de sus representados
            'pagos.ver',       // solo los propios
            'pagos.informar',  // informa un pago con nota escrita, sin adjuntos
            'aporte.avisos.informar', // avisa el pago del aporte con una nota escrita
            'aporte.estado_cuenta',   // solo el estado de cuenta de sus representados
            'historial.ver',   // solo lo autorizado de sus representados
        ],
    ];

    /** Devuelve los nombres de todos los roles definidos en la matriz. */
    public static function roles(): array
    {
        return array_keys(self::POR_ROL);
    }

    /**
     * Devuelve el rango numérico de un rol. Si el rol no está en la lista
     * devolvemos 0, es decir, el rango más bajo posible, para que un rol
     * desconocido nunca tenga más privilegios de los debidos.
     */
    public static function rango(string $rol): int
    {
        return self::RANGO_ROL[$rol] ?? 0;
    }
}
