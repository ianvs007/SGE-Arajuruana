<?php

namespace App\Support;

/**
 * Catálogo canónico de permisos y roles (§5 del prompt maestro).
 *
 * Reglas confirmadas:
 * - El acceso se deniega por defecto; un cargo no recibe acceso universal por su nombre.
 * - Administración crea cuentas; no existe registro público.
 * - Director y Coordinadora también administran cuentas de usuario.
 * - Docente trabaja con sus cursos asignados y publica avisos (incluso generales),
 *   sin acceso a datos personales ni cuentas de todo el colegio.
 * - Responsable familiar consulta solo lo autorizado de sus representados.
 * - Incidencias confidenciales: solo Administración (decisión confirmada).
 *
 * Matriz corregida el 30/09/2026 por dirección del colegio:
 * - Administración y Director: acceso a TODO el sistema.
 * - Coordinadora: cuentas de mensualidades, asistencia diaria, cobro y
 *   validación de pagos, comunicados, descarga de toda la información
 *   económica, verificación de citaciones y de salidas/llegadas.
 * - Docente: comunicados y citaciones a padres, VERIFICA incidencias
 *   disciplinarias (solo lectura y solo sus cursos), asistencia diaria de
 *   sus cursos y salidas/llegadas de sus estudiantes.
 * - Responsable Familiar: estado de cuenta de sus hijos, informa pagos,
 *   comunicados y citaciones, y asistencia de sus hijos.
 */
final class Permisos
{
    /** Todos los permisos del sistema. */
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
        'incidencias.ver',        // consulta de incidencias sin editar (Docente, §30/09/2026)
        'incidencias.confidenciales',
        'citaciones.gestionar',
        'citaciones.ver',
        'avisos.gestionar',
        'avisos.ver',
        // Económico — flujo viejo de cargos/pagos (se conserva para datos históricos)
        'cuentas.gestionar',
        'cuentas.ver',
        'pagos.gestionar',
        'pagos.confirmar',
        'pagos.ver',
        'pagos.informar',
        // Económico Etapa 4 (§14) — prefijo `aporte.*` para no colisionar con
        // `cuota.*` (cuotas de asistencia de la Etapa 2)
        'aporte.parametros',      // configurar monto/meses/vencimiento por gestión
        'aporte.cuotas.gestionar', // generar cuotas y eximir (Administración)
        'aporte.cuotas.ver',       // ver cuotas/estado económico institucional
        'aporte.avisos.gestionar', // validar o rechazar avisos de pago
        'aporte.avisos.informar',  // familia informa pago con nota escrita
        'aporte.pagos.anular',     // anulación trazable de pago validado
        'aporte.estado_cuenta',    // estado de cuenta (familia: solo representados)
        // Transversales
        'reportes.ver',
        'historial.ver',
    ];

    /** Rangos para la salvaguarda anti-escalada de roles (§5). */
    public const RANGO_ROL = [
        'Administración' => 100,
        'Director' => 90,
        'Coordinadora' => 80,
        'Subdirector' => 70,
        'Docente' => 50,
        'Responsable Familiar' => 10,
    ];

    /** Matriz rol → permisos. */
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
        // Acceso a TODO el sistema (decisión del colegio, 30/09/2026).
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
            'cuentas.gestionar',      // cuentas de las mensualidades (módulo histórico)
            'cuentas.ver',
            'pagos.gestionar',        // cobro de mensualidades (módulo histórico)
            'pagos.confirmar',        // valida los pagos (módulo histórico)
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
            'estudiantes.ver',       // acotado a sus cursos asignados (Policy)
            'asistencia.gestionar',  // registra y verifica la asistencia de sus cursos
            'asistencia.ver',
            'salidas.autorizar',     // valida salidas y llegadas de SUS estudiantes
            'salidas.ver',
            'incidencias.ver',       // verifica casos disciplinarios (solo lectura)
            'citaciones.gestionar',  // citaciones a padres, solo alumnos de sus cursos
            'citaciones.ver',
            'avisos.gestionar',      // realiza comunicados, incluidos generales (§5)
            'avisos.ver',
            'historial.ver',         // acotado a alumnos de sus cursos, sin confidenciales
        ],
        'Responsable Familiar' => [
            'estudiantes.ver', // solo sus representados (validación por registro)
            'asistencia.ver',  // verifica la asistencia de SUS hijos (solo lectura)
            'salidas.ver',     // solo las de sus representados (§7: información autorizada)
            'citaciones.ver',  // solo las dirigidas a él
            'avisos.ver',      // solo los que le competen
            'cuentas.ver',     // solo de sus representados
            'pagos.ver',       // solo propios
            'pagos.informar',  // informa pago con nota escrita, sin adjuntos
            'aporte.avisos.informar', // §14: avisa el pago con nota escrita
            'aporte.estado_cuenta',   // §14: solo estado de cuenta de sus representados
            'historial.ver',   // solo lo autorizado de sus representados
        ],
    ];

    public static function roles(): array
    {
        return array_keys(self::POR_ROL);
    }

    public static function rango(string $rol): int
    {
        return self::RANGO_ROL[$rol] ?? 0;
    }
}
