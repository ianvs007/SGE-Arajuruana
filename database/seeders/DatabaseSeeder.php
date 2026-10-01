<?php

namespace Database\Seeders;

use App\Models\AporteParametro;
use App\Models\Asistencia;
use App\Models\Aviso;
use App\Models\AvisoPago;
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

/**
 * Datos de demostración ficticios (§2: contexto boliviano, datos de prueba ficticios).
 * Contraseña de todas las cuentas demo: password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Permisos y matriz de roles sincronizados desde un seeder dedicado,
        // reutilizable en producción sin sembrar datos demo.
        $this->call(RolePermissionSeeder::class);

        // --- Gestión académica configurable (§4) ---
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

        // --- Usuarios demo con los roles confirmados (§5) ---
        $users = [
            ['name' => 'Ana María Justiniano', 'email' => 'administracion@sge.local', 'role' => 'Administración', 'documento' => '3845921 Beni'],
            ['name' => 'Carlos Roca Suárez', 'email' => 'director@sge.local', 'role' => 'Director', 'documento' => '2917455 Beni'],
            ['name' => 'Lucía Vaca Ortiz', 'email' => 'coordinadora@sge.local', 'role' => 'Coordinadora', 'documento' => '4102877 Beni'],
            ['name' => 'Miguel Temo Quete', 'email' => 'subdirector@sge.local', 'role' => 'Subdirector', 'documento' => '3556190 Beni'],
            ['name' => 'Prof. Rosa Cuasace', 'email' => 'docente@sge.local', 'role' => 'Docente', 'documento' => '4438210 Beni'],
            ['name' => 'Juan Pérez Mamani', 'email' => 'padre@sge.local', 'role' => User::ROL_RESPONSABLE, 'documento' => '5123987 Beni', 'telefono' => '70000001'],
            ['name' => 'Elena López Rivero', 'email' => 'madre@sge.local', 'role' => User::ROL_RESPONSABLE, 'documento' => '5298341 Beni', 'telefono' => '70000002'],
        ];

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
            $user->syncRoles([$data['role']]);
        }

        // --- Cursos de la gestión actual (configurables, no fijos en código) ---
        $cursosDef = [
            ['nombre' => '1ro de Primaria', 'nivel' => 'Primaria', 'grado' => '1ro', 'paralelo' => 'A', 'turno' => 'manana', 'orden' => 1],
            ['nombre' => '3ro de Secundaria', 'nivel' => 'Secundaria', 'grado' => '3ro', 'paralelo' => 'A', 'turno' => 'mixto', 'orden' => 2],
        ];

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

        // Curso de la gestión anterior (para demostrar historial entre gestiones)
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

        // --- Horario demo: 3ro Secundaria tiene clases por la tarde (§4) ---
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

        // --- Docente asignado a su curso (§5: valida pertenencia curso-docente) ---
        $docente = User::where('email', 'docente@sge.local')->first();
        $cursoMixto->docentes()->syncWithoutDetaching([
            $docente->id => ['gestion_id' => $gestion->id, 'rol_docente' => 'docente_aula'],
        ]);

        // --- Alumnos: identidad + inscripciones separadas (§7) ---
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
                    'curso_id' => $cursos[$def['curso']]->id, // transición
                    'estado' => 'activo',
                ]
            );

            // Padre y madre tienen cuentas separadas, vinculadas a los mismos alumnos,
            // sin duplicar la obligación de aporte (§5, §14).
            $estudiante->responsables()->syncWithoutDetaching([
                $padre->id => ['parentesco' => 'padre', 'es_principal' => true],
                $madre->id => ['parentesco' => 'madre', 'es_principal' => false],
            ]);

            // Inscripción en la gestión actual
            Inscripcion::updateOrCreate(
                ['estudiante_id' => $estudiante->id, 'gestion_id' => $gestion->id],
                [
                    'curso_id' => $cursos[$def['curso']]->id,
                    'estado' => 'activa',
                    'fecha_inscripcion' => '2026-01-20',
                ]
            );

            // Inscripción histórica del mayor (demuestra historial entre gestiones, §7)
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

        // --- Asistencia demo (estados confirmados §9; turno manana/tarde) ---
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

        // --- Excepción de calendario demo (§9): jornada sin clases, no genera ausentes ---
        CalendarioExcepcion::updateOrCreate(
            ['gestion_id' => $gestion->id, 'curso_id' => null, 'fecha' => '2026-02-02', 'tipo' => CalendarioExcepcion::TIPO_SIN_CLASES],
            ['motivo' => 'Primer día de inscripción y organización interna (demo)']
        );

        // --- Categorías de incidencias demo (§11): configurables por Administración ---
        foreach (['Convivencia y disciplina', 'Puntualidad y asistencia', 'Cuidado del material', 'Situación familiar'] as $nombreCategoria) {
            IncidenciaCategoria::updateOrCreate(['nombre' => $nombreCategoria], ['activa' => true]);
        }

        // Incidencia demo no confidencial + una confidencial (solo visible para Administración)
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
                'descripcion' => 'Caso confidencial ficticio: solo Administración puede ver este detalle (§11).',
                'estado_seguimiento' => 'abierta',
                'registrado_por' => User::where('email', 'administracion@sge.local')->first()->id,
            ]
        );

        // --- Salida demo con flujo completo (§10): autorizada → salida efectiva → retorno ---
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

        // --- Citación demo con acuerdos y seguimiento (§12) ---
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

        // --- Avisos institucionales demo (Etapa 5, §13) ---
        $admin = User::where('email', 'administracion@sge.local')->first();

        // General, publicado y con destinatarios materializados.
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
        // publicar() es idempotente: materializa destinatarios sin duplicar (§13).
        NotificacionService::publicar($avisoBienvenida);

        // Dirigido a un curso, con confirmación de lectura OPCIONAL (no bloqueante).
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

        // Dirigido a la familia de un alumno (aviso específico, §13).
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
        // Etapa 4 (§14, §15): módulo económico demo
        // =====================================================================

        // Parámetros de aporte de la gestión actual: Bs 40, feb–nov, día 10.
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

        // Cuotas de los alumnos inscritos (idempotente; obliga por alumno).
        AporteService::generarCuotasDeGestion($gestion, $admin);

        // Flujo demo completo: aviso del padre → validado por Administración con
        // distribución entre dos hijos (§20.12), comprobante interno emitido.
        if (! AvisoPago::where('referencia', 'AVI-DEMO-000001')->exists()) {
            $avisoDemo = AvisoPago::create([
                'referencia' => 'AVI-DEMO-000001',
                'padre_id' => $padre->id,
                'gestion_id' => $gestion->id,
                'monto_declarado' => '80.00',
                'nota' => 'Depositamos Bs 80 en la caja del colegio; corresponde a febrero de María Fernanda y José Luis (demo).',
                'estado' => 'pendiente',
                'informado_en' => now()->subDays(2),
            ]);

            $cuotaHija = CuotaAporte::where('gestion_id', $gestion->id)
                ->where('estudiante_id', $estudiante1->id)->where('mes', 2)->first();
            $cuotaHijo = CuotaAporte::where('gestion_id', $gestion->id)
                ->where('estudiante_id', $estudiante2->id)->where('mes', 2)->first();

            if ($cuotaHija && $cuotaHijo) {
                AporteService::validarAviso($avisoDemo, [
                    ['cuota_id' => $cuotaHija->id, 'monto' => '40.00'],
                    ['cuota_id' => $cuotaHijo->id, 'monto' => '40.00'],
                ], $admin, 'Validado contra depósito en caja (demo).');
            }
        }

        // Aviso pendiente demo (muestra la cola de validación de Administración):
        // un aviso pendiente NO reduce deuda ni genera comprobante (§20.13).
        AvisoPago::firstOrCreate(
            ['referencia' => 'AVI-DEMO-000002'],
            [
                'padre_id' => $madre->id,
                'gestion_id' => $gestion->id,
                'monto_declarado' => '40.00',
                'nota' => 'Pagué marzo de Ana Gabriela en la caja (demo). Favor validar.',
                'estado' => 'pendiente',
                'informado_en' => now()->subDay(),
            ]
        );
    }
}
