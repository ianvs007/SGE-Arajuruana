<?php

namespace Database\Seeders;

use App\Models\AporteParametro;
use App\Models\Asistencia;
use App\Models\Aviso;
use App\Models\AvisoPago;
use App\Models\AvisoPagoCuota;
use App\Models\CalendarioExcepcion;
use App\Models\Citacion;
use App\Models\CuotaAporte;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\HorarioCurso;
use App\Models\Incidencia;
use App\Models\IncidenciaCategoria;
use App\Models\Inscripcion;
use App\Models\SalidaEstudiante;
use App\Models\User;
use App\Services\AporteService;
use App\Services\NotificacionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeder principal: carga datos de demostración ficticios.
 *
 * Con este seeder llenamos la base de datos con un escenario de prueba
 * ambientado en el contexto boliviano (nombres, carnets del Beni, montos en
 * bolivianos), pero con datos totalmente inventados. Sirve para probar el
 * sistema y para mostrarlo en la defensa sin usar información real de alumnos.
 *
 * Carga, en este orden: roles y permisos, dos gestiones (la actual y la
 * anterior), un usuario por cada rol, cursos con horarios, un docente asignado,
 * tres hermanos inscritos con sus padres vinculados, y ejemplos de asistencia,
 * calendario, incidencias, salidas, citaciones, avisos y del módulo económico.
 *
 * Usamos updateOrCreate() y firstOrCreate() casi siempre, de modo que el seeder
 * se puede ejecutar varias veces sin duplicar registros.
 *
 * Contraseña de todas las cuentas demo: password
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta la carga de datos de demostración.
     */
    public function run(): void
    {
        // Los permisos y la matriz de roles se sincronizan desde un seeder
        // aparte, que también se puede usar en producción sin cargar datos demo.
        $this->call(RolePermissionSeeder::class);

        // --- Gestión académica configurable ---
        // Creamos la gestión 2026 como la vigente y la 2025 como histórica,
        // para poder demostrar el historial de un alumno entre años.
        $gestion = Gestion::updateOrCreate(
            ['anio' => 2026],
            [
                'nombre' => 'Gestión 2026',
                'fecha_inicio' => '2026-02-02',
                'fecha_fin' => '2026-11-30',
                'es_actual' => true,
                'activa' => true,
            ]
        );

        $gestionAnterior = Gestion::updateOrCreate(
            ['anio' => 2025],
            [
                'nombre' => 'Gestión 2025',
                'fecha_inicio' => '2025-02-03',
                'fecha_fin' => '2025-11-28',
                'es_actual' => false,
                'activa' => false,
            ]
        );

        // --- Usuarios demo, uno por cada rol confirmado con el colegio ---
        // Padre y madre tienen el rol Responsable Familiar y cuentas separadas.
        $users = [
            ['name' => 'Ana María Justiniano', 'email' => 'administracion@sge.local', 'role' => 'Administración', 'documento' => '3845921 Beni'],
            ['name' => 'Carlos Roca Suárez', 'email' => 'director@sge.local', 'role' => 'Director', 'documento' => '2917455 Beni'],
            ['name' => 'Lucía Vaca Ortiz', 'email' => 'coordinadora@sge.local', 'role' => 'Coordinadora', 'documento' => '4102877 Beni'],
            ['name' => 'Miguel Temo Quete', 'email' => 'subdirector@sge.local', 'role' => 'Subdirector', 'documento' => '3556190 Beni'],
            ['name' => 'Prof. Rosa Cuasace', 'email' => 'docente@sge.local', 'role' => 'Docente', 'documento' => '4438210 Beni'],
            ['name' => 'Juan Pérez Mamani', 'email' => 'padre@sge.local', 'role' => User::ROL_RESPONSABLE, 'documento' => '5123987 Beni', 'telefono' => '70000001'],
            ['name' => 'Elena López Rivero', 'email' => 'madre@sge.local', 'role' => User::ROL_RESPONSABLE, 'documento' => '5298341 Beni', 'telefono' => '70000002'],
        ];

        // Creamos (o actualizamos) cada usuario buscando por correo, con la
        // contraseña cifrada y el correo ya verificado para entrar directo.
        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'documento' => $data['documento'],
                    'telefono' => $data['telefono'] ?? null,
                    'password' => Hash::make('password'),
                    'activo' => true,
                    'email_verified_at' => now(),
                ]
            );
            // syncRoles deja al usuario solo con este rol, aunque antes tuviera otro.
            $user->syncRoles([$data['role']]);
        }

        // --- Cursos de la gestión actual (se configuran desde el sistema, no están fijos en el código) ---
        // Uno de primaria en turno mañana y uno de secundaria en turno mixto,
        // para probar la asistencia en ambos turnos.
        $cursosDef = [
            ['nombre' => '1ro de Primaria', 'nivel' => 'Primaria', 'grado' => '1ro', 'paralelo' => 'A', 'turno' => 'manana', 'orden' => 1],
            ['nombre' => '3ro de Secundaria', 'nivel' => 'Secundaria', 'grado' => '3ro', 'paralelo' => 'A', 'turno' => 'mixto', 'orden' => 2],
        ];

        // Guardamos los cursos creados en un arreglo indexado por nombre para
        // usarlos más abajo al inscribir a los alumnos.
        $cursos = [];
        foreach ($cursosDef as $def) {
            $cursos[$def['nombre']] = Curso::updateOrCreate(
                ['gestion_id' => $gestion->id, 'nombre' => $def['nombre'], 'paralelo' => $def['paralelo']],
                [
                    'nivel' => $def['nivel'],
                    'grado' => $def['grado'],
                    'turno' => $def['turno'],
                    'orden' => $def['orden'],
                    'anio_lectivo' => $gestion->anio,
                    'activo' => true,
                ]
            );
        }

        // Curso de la gestión anterior, ya inactivo, para demostrar el historial entre gestiones.
        $cursoAnterior = Curso::updateOrCreate(
            ['gestion_id' => $gestionAnterior->id, 'nombre' => '3ro de Secundaria', 'paralelo' => 'A'],
            [
                'nivel' => 'Secundaria',
                'grado' => '3ro',
                'turno' => 'mixto',
                'orden' => 2,
                'anio_lectivo' => $gestionAnterior->anio,
                'activo' => false,
            ]
        );

        // --- Horario demo: 3ro de Secundaria tiene clases el lunes en la mañana y
        // también en la tarde; 1ro de Primaria solo en la mañana ---
        $cursoMixto = $cursos['3ro de Secundaria'];
        HorarioCurso::updateOrCreate(
            ['curso_id' => $cursoMixto->id, 'dia_semana' => 1, 'turno' => 'manana', 'hora_inicio' => '08:00:00'],
            ['hora_fin' => '12:00:00', 'activo' => true, 'vigente_desde' => $gestion->fecha_inicio]
        );
        HorarioCurso::updateOrCreate(
            ['curso_id' => $cursoMixto->id, 'dia_semana' => 1, 'turno' => 'tarde', 'hora_inicio' => '14:00:00'],
            ['hora_fin' => '17:00:00', 'activo' => true, 'vigente_desde' => $gestion->fecha_inicio]
        );
        $cursoPrimaria = $cursos['1ro de Primaria'];
        HorarioCurso::updateOrCreate(
            ['curso_id' => $cursoPrimaria->id, 'dia_semana' => 1, 'turno' => 'manana', 'hora_inicio' => '08:00:00'],
            ['hora_fin' => '12:00:00', 'activo' => true, 'vigente_desde' => $gestion->fecha_inicio]
        );

        // --- Docente asignado a su curso ---
        // Sirve para probar que el docente solo puede trabajar con los cursos
        // que tiene asignados. syncWithoutDetaching no borra otras asignaciones.
        $docente = User::where('email', 'docente@sge.local')->first();
        $cursoMixto->docentes()->syncWithoutDetaching([
            $docente->id => ['gestion_id' => $gestion->id, 'rol_docente' => 'docente_aula'],
        ]);

        // --- Alumnos: los datos de identidad y las inscripciones se guardan por separado ---
        // Cargamos tres hermanos para probar que una familia con varios hijos
        // se maneja bien (por ejemplo, un solo pago para dos hijos).
        $padre = User::where('email', 'padre@sge.local')->first();
        $madre = User::where('email', 'madre@sge.local')->first();

        $estudiantesDef = [
            ['codigo' => 'EST-2026-001', 'nombres' => 'María Fernanda', 'apellidos' => 'Pérez López', 'sexo' => 'Femenino', 'fn' => '2012-05-10', 'curso' => '1ro de Primaria'],
            ['codigo' => 'EST-2026-002', 'nombres' => 'José Luis', 'apellidos' => 'Pérez López', 'sexo' => 'Masculino', 'fn' => '2010-08-22', 'curso' => '3ro de Secundaria'],
            ['codigo' => 'EST-2026-003', 'nombres' => 'Ana Gabriela', 'apellidos' => 'Pérez López', 'sexo' => 'Femenino', 'fn' => '2013-11-03', 'curso' => '1ro de Primaria'],
        ];

        foreach ($estudiantesDef as $def) {
            $estudiante = Estudiante::updateOrCreate(
                ['codigo' => $def['codigo']],
                [
                    'nombres' => $def['nombres'],
                    'apellidos' => $def['apellidos'],
                    'fecha_nacimiento' => $def['fn'],
                    'sexo' => $def['sexo'],
                    'curso_id' => $cursos[$def['curso']]->id, // se mantiene por compatibilidad mientras se pasa a usar inscripciones
                    'estado' => 'activo',
                ]
            );

            // Padre y madre tienen cuentas separadas, vinculadas a los mismos alumnos,
            // sin duplicar la obligación de aporte (la cuota es por alumno, no por padre).
            $estudiante->responsables()->syncWithoutDetaching([
                $padre->id => ['parentesco' => 'padre', 'es_principal' => true],
                $madre->id => ['parentesco' => 'madre', 'es_principal' => false],
            ]);

            // Inscripción en la gestión actual.
            Inscripcion::updateOrCreate(
                ['estudiante_id' => $estudiante->id, 'gestion_id' => $gestion->id],
                [
                    'curso_id' => $cursos[$def['curso']]->id,
                    'estado' => 'activa',
                    'fecha_inscripcion' => '2026-01-20',
                ]
            );

            // Inscripción del hermano mayor en la gestión anterior, para mostrar su historial entre gestiones.
            if ($def['codigo'] === 'EST-2026-002') {
                Inscripcion::updateOrCreate(
                    ['estudiante_id' => $estudiante->id, 'gestion_id' => $gestionAnterior->id],
                    [
                        'curso_id' => $cursoAnterior->id,
                        'estado' => 'activa',
                        'fecha_inscripcion' => '2025-01-21',
                    ]
                );
            }
        }

        // --- Asistencia demo: un registro "presente" de hoy en el turno mañana ---
        // Los estados posibles (presente, ausente, etc.) son los confirmados con el colegio.
        $estudiante1 = Estudiante::where('codigo', 'EST-2026-001')->first();
        Asistencia::updateOrCreate(
            ['estudiante_id' => $estudiante1->id, 'fecha' => today()->toDateString(), 'turno' => 'manana'],
            [
                'curso_id' => $cursoPrimaria->id,
                'inscripcion_id' => $estudiante1->inscripcionEn($gestion)?->id,
                'estado' => 'presente',
                'registrado_por' => User::where('email', 'administracion@sge.local')->first()->id,
            ]
        );

        // --- Excepción de calendario demo: jornada sin clases para todo el colegio
        // (curso_id null), por lo que ese día no se cuentan ausencias ---
        CalendarioExcepcion::updateOrCreate(
            ['gestion_id' => $gestion->id, 'curso_id' => null, 'fecha' => '2026-02-02', 'tipo' => CalendarioExcepcion::TIPO_SIN_CLASES],
            ['motivo' => 'Primer día de inscripción y organización interna (demo)']
        );

        // --- Categorías de incidencias demo (luego Administración puede cambiarlas) ---
        foreach (['Convivencia y disciplina', 'Puntualidad y asistencia', 'Cuidado del material', 'Situación familiar'] as $nombreCategoria) {
            IncidenciaCategoria::updateOrCreate(['nombre' => $nombreCategoria], ['activa' => true]);
        }

        // Una incidencia demo normal y otra confidencial (esta última solo la ve
        // Administración), para probar el control de visibilidad.
        $estudiante2 = Estudiante::where('codigo', 'EST-2026-002')->first();
        $categoriaConvivencia = IncidenciaCategoria::where('nombre', 'Convivencia y disciplina')->first();
        Incidencia::updateOrCreate(
            ['estudiante_id' => $estudiante2->id, 'fecha' => today()->subDays(7)->toDateString(), 'categoria_id' => $categoriaConvivencia->id],
            [
                'tipo' => $categoriaConvivencia->nombre,
                'confidencial' => false,
                'descripcion' => 'Incidencia ficticia de demostración: conflicto menor en el recreo, resuelto con diálogo.',
                'medida_accion' => 'Llamado de atención y compromiso verbal (demo).',
                'estado_seguimiento' => 'en_seguimiento',
                'registrado_por' => User::where('email', 'administracion@sge.local')->first()->id,
            ]
        );
        Incidencia::updateOrCreate(
            ['estudiante_id' => $estudiante2->id, 'fecha' => today()->subDays(3)->toDateString(), 'categoria_id' => IncidenciaCategoria::where('nombre', 'Situación familiar')->first()->id],
            [
                'tipo' => 'Situación familiar',
                'confidencial' => true,
                'descripcion' => 'Caso confidencial ficticio: solo Administración puede ver este detalle.',
                'estado_seguimiento' => 'abierta',
                'registrado_por' => User::where('email', 'administracion@sge.local')->first()->id,
            ]
        );

        // --- Salida demo con el flujo completo: autorizada → salida efectiva → retorno ---
        // La autoriza el Director y Administración registra la salida, verifica
        // el documento de quien retira y registra el retorno del alumno.
        $director = User::where('email', 'director@sge.local')->first();
        $salida = SalidaEstudiante::updateOrCreate(
            ['estudiante_id' => $estudiante1->id, 'fecha' => today()->subDay()->toDateString(), 'estado' => 'retornada'],
            [
                'motivo' => 'salud',
                'autorizado_por' => $director->id,
                'autorizado_en' => today()->subDay()->setTime(9, 0),
                'hora_salida' => '09:30:00',
                'salida_en' => today()->subDay()->setTime(9, 32),
                'salida_registrado_por' => User::where('email', 'administracion@sge.local')->first()->id,
                'responsable_retiro' => 'Juan Pérez Mamani (padre)',
                'documento_responsable' => '5123987 Beni',
                'verificacion_retiro' => 'Documento verificado a la vista por Administración (demo)',
                'hora_retorno' => '11:15:00',
                'retorno_en' => today()->subDay()->setTime(11, 17),
                'retorno_registrado_por' => User::where('email', 'administracion@sge.local')->first()->id,
                'registrado_por' => $director->id,
            ]
        );

        // --- Citación demo con responsable de seguimiento y fecha de revisión ---
        // La genera la docente para dentro de tres días y queda pendiente.
        Citacion::updateOrCreate(
            ['estudiante_id' => $estudiante2->id, 'fecha' => today()->addDays(3)->toDateString(), 'hora' => '15:00:00'],
            [
                'padre_id' => User::where('email', 'padre@sge.local')->first()->id,
                'motivo' => 'Seguimiento de convivencia (demo)',
                'descripcion' => 'Se convoca al responsable para conversar sobre el seguimiento del alumno. Texto ficticio de demostración.',
                'estado' => 'pendiente',
                'seguimiento_responsable_id' => User::where('email', 'administracion@sge.local')->first()->id,
                'fecha_revision' => today()->addDays(10)->toDateString(),
                'generado_por' => User::where('email', 'docente@sge.local')->first()->id,
            ]
        );

        // --- Avisos institucionales demo (etapa 5) ---
        // Cargamos tres avisos con distinta audiencia: para todos, para un
        // curso y para la familia de un alumno.
        $admin = User::where('email', 'administracion@sge.local')->first();

        // Aviso general, publicado y con sus destinatarios ya guardados.
        $avisoBienvenida = Aviso::updateOrCreate(
            ['titulo' => 'Bienvenida al Sistema de Gestión Educativa'],
            [
                'contenido' => 'Este es el canal oficial de avisos institucionales de la Unidad Educativa Arajuruana Fe y Alegría, San Ignacio de Moxos.',
                'tipo' => 'institucional',
                'audiencia' => 'todos',
                'requiere_confirmacion' => false,
                'creado_por' => $admin->id,
            ]
        );
        // publicar() se puede llamar varias veces sin problema: guarda la lista
        // de destinatarios del aviso sin duplicarlos.
        NotificacionService::publicar($avisoBienvenida);

        // Aviso dirigido a un curso, con confirmación de lectura OPCIONAL (no bloquea nada).
        $avisoCurso = Aviso::firstOrCreate(
            ['titulo' => 'Reunión de responsables — 3ro de Secundaria'],
            [
                'contenido' => 'Se convoca a los responsables de 3ro de Secundaria a la reunión informativa del primer bimestre (demo). Texto ficticio.',
                'tipo' => 'administrativo',
                'audiencia' => 'curso',
                'curso_id' => $cursoMixto->id,
                'requiere_confirmacion' => true,
                'confirmar_antes' => today()->addDays(7)->toDateString(),
                'creado_por' => $admin->id,
            ]
        );
        NotificacionService::publicar($avisoCurso);

        // Aviso dirigido solo a la familia de un alumno (los responsables de Ana Gabriela).
        $avisoFamilia = Aviso::firstOrCreate(
            ['titulo' => 'Recordatorio de aporte — Ana Gabriela'],
            [
                'contenido' => 'Recordatorio amistoso sobre el estado de cuenta del aporte mensual de su representada (demo). Texto ficticio.',
                'tipo' => 'economico',
                'audiencia' => 'familia',
                'estudiante_id' => Estudiante::where('codigo', 'EST-2026-003')->value('id'),
                'requiere_confirmacion' => false,
                'creado_por' => $admin->id,
            ]
        );
        NotificacionService::publicar($avisoFamilia);

        // =====================================================================
        // Etapa 4: datos demo del módulo económico (aporte mensual)
        // =====================================================================

        // Parámetros de aporte de la gestión actual: Bs 40 por mes, de febrero
        // a noviembre, con vencimiento el día 10 de cada mes.
        AporteParametro::updateOrCreate(
            ['gestion_id' => $gestion->id],
            [
                'monto_mensual' => 40.00,
                'mes_inicio' => 2,
                'mes_fin' => 11,
                'dia_vencimiento' => 10,
                'activo' => true,
            ]
        );

        // Generamos las cuotas mensuales de cada alumno inscrito. El servicio no
        // duplica cuotas si se ejecuta de nuevo, y la obligación es por alumno.
        AporteService::generarCuotasDeGestion($gestion, $admin);

        // Flujo demo completo: el padre informa que pagó con QR febrero de sus
        // dos hijos (Bs 40 cada uno) y Administración, después de verificar el
        // ingreso en su banco, lo valida con el número de operación. Solo se
        // crea si todavía no existe, para no repetir el pago.
        $cuotaHija = CuotaAporte::where('gestion_id', $gestion->id)
            ->where('estudiante_id', $estudiante1->id)->where('mes', 2)->first();
        $cuotaHijo = CuotaAporte::where('gestion_id', $gestion->id)
            ->where('estudiante_id', $estudiante2->id)->where('mes', 2)->first();

        if ($cuotaHija && $cuotaHijo && ! AvisoPago::where('referencia', 'AVI-DEMO-000001')->exists()) {
            $avisoDemo = AvisoPago::create(array_merge([
                'referencia' => 'AVI-DEMO-000001',
                'padre_id' => $padre->id,
                'gestion_id' => $gestion->id,
                'monto_declarado' => '80.00',
                'nota' => 'Pago de febrero de María Fernanda y José Luis (demo).',
                'estado' => 'pendiente',
                'informado_en' => now()->subDays(2),
                'fecha_pago' => now()->subDays(2)->toDateString(),
            ], $this->comprobanteDemo('AVI-DEMO-000001', 'Bs 80,00')));

            foreach ([$cuotaHija, $cuotaHijo] as $cuota) {
                AvisoPagoCuota::create(['aviso_id' => $avisoDemo->id, 'cuota_id' => $cuota->id, 'monto' => '40.00']);
            }

            AporteService::validarAviso($avisoDemo, [], $admin,
                'Ingreso verificado en la plataforma del banco (demo).', 'DEMO-OP-0001');
        }

        // Aviso pendiente demo, para que la bandeja de validación de
        // Administración no aparezca vacía: la madre informa que pagó con QR
        // marzo de Ana Gabriela. Mientras siga pendiente NO reduce la deuda.
        $cuotaMarzo = CuotaAporte::where('gestion_id', $gestion->id)
            ->whereHas('estudiante', fn ($q) => $q->where('codigo', 'EST-2026-003'))
            ->where('mes', 3)->first();

        if ($cuotaMarzo && ! AvisoPago::where('referencia', 'AVI-DEMO-000002')->exists()) {
            $avisoPendiente = AvisoPago::create(array_merge([
                'referencia' => 'AVI-DEMO-000002',
                'padre_id' => $madre->id,
                'gestion_id' => $gestion->id,
                'monto_declarado' => '40.00',
                'nota' => 'Pagué marzo de Ana Gabriela con el QR (demo). Favor validar.',
                'estado' => 'pendiente',
                'informado_en' => now()->subDay(),
                'fecha_pago' => now()->subDay()->toDateString(),
            ], $this->comprobanteDemo('AVI-DEMO-000002', 'Bs 40,00')));

            AvisoPagoCuota::create(['aviso_id' => $avisoPendiente->id, 'cuota_id' => $cuotaMarzo->id, 'monto' => '40.00']);
        }
    }

    /**
     * Guarda en el disco privado un comprobante PDF de demostración y devuelve
     * los campos del aviso que lo describen.
     *
     * @return array<string, mixed>
     */
    private function comprobanteDemo(string $referencia, string $monto): array
    {
        $texto = "Comprobante de demostracion {$referencia} - {$monto}";
        $contenido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 400 120]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            .'4 0 obj<</Length '.(strlen($texto) + 30).">>stream\nBT /F1 12 Tf 20 60 Td ({$texto}) Tj ET\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        $ruta = 'comprobantes/demo/'.$referencia.'.pdf';
        Storage::disk('local')->put($ruta, $contenido);

        return [
            'comprobante_ruta' => $ruta,
            'comprobante_nombre' => $referencia.'.pdf',
            'comprobante_mime' => 'application/pdf',
            'comprobante_tamano' => strlen($contenido),
            'comprobante_hash' => hash('sha256', $contenido),
        ];
    }
}
