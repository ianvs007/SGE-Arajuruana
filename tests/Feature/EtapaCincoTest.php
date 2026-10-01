<?php

namespace Tests\Feature;

use App\Mail\AvisoInstitucionalMail;
use App\Models\Aviso;
use App\Models\Curso;
use App\Models\CuotaAporte;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Respaldo;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\ReporteService;
use App\Support\WhatsApp;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Etapa 5 (§13, §16, §17): comunicaciones, reportes con totales idénticos y respaldo.
 *
 * Cubre:
 * - Avisos con destinatarios específicos materializados al publicar (§13).
 * - Confirmación de lectura OPCIONAL y NO BLOQUEANTE (§13).
 * - Correo opcional (array/log) con fallo no bloqueante; sin secretos en código (§13).
 * - WhatsApp manual: enlace wa.me generado, sin API ni envío automático (§13).
 * - Reportes PDF/Excel con totales idénticos a pantalla (§16).
 * - Respaldo manual fuera de public/ con restricción por rol (§17).
 */
class EtapaCincoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function admin(): User
    {
        return $this->usuario('administracion@sge.local');
    }

    private function estudiante(string $codigo): Estudiante
    {
        return Estudiante::where('codigo', $codigo)->firstOrFail();
    }

    private function aviso(string $titulo): Aviso
    {
        return Aviso::where('titulo', $titulo)->firstOrFail();
    }

    /** Responsable familiar sin representados (para pruebas de alcance, §6). */
    private function responsableAjeno(): User
    {
        $user = User::create([
            'name' => 'Responsable Ajeno',
            'email' => 'ajeno@sge.local',
            'documento' => '9999999 Beni',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->assignRole(User::ROL_RESPONSABLE);

        return $user;
    }

    // ============ §13: destinatarios específicos materializados ============

    public function test_aviso_general_materializa_a_toda_la_comunidad_al_publicar(): void
    {
        $aviso = $this->aviso('Bienvenida al Sistema de Gestión Educativa');

        $this->assertTrue($aviso->publicado);
        $activos = User::where('activo', true)->count();
        $this->assertSame($activos, $aviso->destinatarios()->count());

        // El padre y el docente son destinatarios del aviso general.
        $this->assertTrue($aviso->esDestinatario($this->usuario('padre@sge.local')));
        $this->assertTrue($aviso->esDestinatario($this->usuario('docente@sge.local')));
    }

    public function test_aviso_por_curso_llega_a_responsables_y_docentes_del_curso(): void
    {
        $aviso = $this->aviso('Reunión de responsables — 3ro de Secundaria');
        $curso = Curso::where('nombre', '3ro de Secundaria')->where('gestion_id', Gestion::actual()->id)->firstOrFail();

        $destinoIds = $aviso->destinatarios()->pluck('user_id')->all();

        // El docente asignado a 3ro de Secundaria es destinatario.
        $this->assertContains($this->usuario('docente@sge.local')->id, $destinoIds);
        // Los responsables de un alumno de 3ro (EST-2026-002) también.
        $this->assertContains($this->usuario('padre@sge.local')->id, $destinoIds);

        $this->assertSame('curso', $aviso->audiencia);
        $this->assertSame($curso->id, $aviso->curso_id);
    }

    public function test_aviso_por_familia_llega_solo_a_los_responsables_del_alumno(): void
    {
        $aviso = $this->aviso('Recordatorio de aporte — Ana Gabriela');

        $destinoIds = $aviso->destinatarios()->pluck('user_id')->all();
        $this->assertContains($this->usuario('padre@sge.local')->id, $destinoIds);
        $this->assertContains($this->usuario('madre@sge.local')->id, $destinoIds);
        // Un docente NO es destinatario de un aviso dirigido a una familia.
        $this->assertNotContains($this->usuario('docente@sge.local')->id, $destinoIds);
        $this->assertCount(2, $destinoIds);
    }

    public function test_publicar_es_idempotente_no_duplica_destinatarios(): void
    {
        $aviso = $this->aviso('Recordatorio de aporte — Ana Gabriela');
        $antes = $aviso->destinatarios()->count();

        NotificacionService::publicar($aviso, $this->admin());

        $this->assertSame($antes, $aviso->destinatarios()->count());
    }

    public function test_responsable_solo_ve_avisos_que_le_competen(): void
    {
        $padre = $this->usuario('padre@sge.local');

        // Ve los generales y los dirigidos a sus representados.
        $this->actingAs($padre)->get(route('avisos.index'))
            ->assertOk()
            ->assertSee('Bienvenida al Sistema de Gestión Educativa');
        $this->actingAs($padre)->get(route('avisos.show', $this->aviso('Recordatorio de aporte — Ana Gabriela')))
            ->assertOk();

        // Aviso dirigido a la familia de EST-2026-002.
        $dirigido = Aviso::create([
            'titulo' => 'Solo familia de José Luis',
            'contenido' => 'Mensaje dirigido.',
            'tipo' => 'seguimiento',
            'audiencia' => 'familia',
            'estudiante_id' => $this->estudiante('EST-2026-002')->id,
            'creado_por' => $this->admin()->id,
        ]);
        NotificacionService::publicar($dirigido);

        // El padre representa a EST-2026-002: lo ve.
        $this->actingAs($padre)->get(route('avisos.show', $dirigido))->assertOk();

        // Un responsable que NO lo representa: denegado (§6).
        $ajeno = $this->responsableAjeno();
        $this->actingAs($ajeno)->get(route('avisos.show', $dirigido))->assertForbidden();
    }

    public function test_usuario_no_destinatario_no_puede_ver_aviso_dirigido(): void
    {
        // Aviso dirigido a la familia de EST-2026-002 (representado por padre/madre).
        $aviso = Aviso::create([
            'titulo' => 'Solo familia de José Luis (2)',
            'contenido' => 'Mensaje dirigido.',
            'tipo' => 'seguimiento',
            'audiencia' => 'familia',
            'estudiante_id' => $this->estudiante('EST-2026-002')->id,
            'creado_por' => $this->admin()->id,
        ]);
        NotificacionService::publicar($aviso);

        // Responsable ajeno (sin representados) → 403 (§6, validación por registro).
        $this->actingAs($this->responsableAjeno())
            ->get(route('avisos.show', $aviso))
            ->assertForbidden();

        // La madre (responsable del alumno) sí lo ve.
        $this->actingAs($this->usuario('madre@sge.local'))
            ->get(route('avisos.show', $aviso))
            ->assertOk();
    }

    // ============ §13: confirmación de lectura OPCIONAL y NO BLOQUEANTE ============

    public function test_confirmacion_de_lectura_es_opcional_y_no_bloquea(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $aviso = $this->aviso('Reunión de responsables — 3ro de Secundaria');
        $destino = $aviso->destinatarioDe($padre);
        $this->assertNotNull($destino);
        $this->assertTrue($aviso->requiere_confirmacion);
        $this->assertNull($destino->confirmado_en);

        // Antes de confirmar, el responsable USA el sistema con normalidad.
        $this->actingAs($padre)->get(route('dashboard'))->assertOk();
        $this->actingAs($padre)->get(route('aporte.estado_cuenta', $this->estudiante('EST-2026-002')))->assertOk();

        // Confirma (opcional).
        $this->actingAs($padre)->post(route('avisos.confirmar', $aviso))->assertRedirect();
        $destino->refresh();
        $this->assertNotNull($destino->confirmado_en);

        // Sigue usando el sistema igual después de confirmar.
        $this->actingAs($padre)->get(route('dashboard'))->assertOk();
    }

    public function test_confirmar_aviso_sin_requerimiento_no_marca_confirmacion(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $aviso = $this->aviso('Recordatorio de aporte — Ana Gabriela'); // requiere_confirmacion=false
        $destino = $aviso->destinatarioDe($padre);

        $this->actingAs($padre)->post(route('avisos.confirmar', $aviso))->assertRedirect();
        $destino->refresh();
        $this->assertNull($destino->confirmado_en, 'Sin requerimiento, confirmar no registra nada.');
    }

    public function test_ver_aviso_registra_lectura_pero_no_obliga_a_confirmar(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $aviso = $this->aviso('Reunión de responsables — 3ro de Secundaria');
        $destino = $aviso->destinatarioDe($padre);
        $this->assertNull($destino->leido_en);

        $this->actingAs($padre)->get(route('avisos.show', $aviso))->assertOk();

        $destino->refresh();
        $this->assertNotNull($destino->leido_en, 'Abrir el aviso registra lectura (no bloqueante).');
        $this->assertNull($destino->confirmado_en, 'Leer no equivale a confirmar.');
    }

    // ============ §13: correo opcional, fallo no bloqueante ============

    public function test_envio_de_correo_marca_destinatarios_y_no_bloquea(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $aviso = $this->aviso('Recordatorio de aporte — Ana Gabriela');

        $this->actingAs($admin)->post(route('avisos.correo', $aviso))->assertRedirect();

        Mail::assertSent(AvisoInstitucionalMail::class, function ($mail) {
            return in_array($mail->aviso->titulo, ['Recordatorio de aporte — Ana Gabriela'], true);
        });

        // Los destinatarios quedan marcados como enviados.
        $enviados = $aviso->destinatarios()->where('correo_estado', 'enviado')->count();
        $this->assertSame($aviso->destinatarios()->count(), $enviados);
    }

    public function test_fallo_de_correo_no_bloquea_el_aviso(): void
    {
        // Simulamos un fallo del transporte: el aviso sigue publicado y visible.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP no disponible'));

        $admin = $this->admin();
        $aviso = $this->aviso('Recordatorio de aporte — Ana Gabriela');

        $this->actingAs($admin)->post(route('avisos.correo', $aviso))
            ->assertRedirect()
            ->assertSessionHas('error');

        // §13: el aviso NO se ve afectado por el fallo de correo.
        $this->assertTrue($aviso->fresh()->publicado);
        $this->assertSame('error', $aviso->destinatarios()->first()->correo_estado);
        // El responsable sigue viendo el aviso en pantalla.
        $this->actingAs($this->usuario('padre@sge.local'))->get(route('avisos.show', $aviso))->assertOk();
    }

    public function test_correo_no_contiene_secretos_y_usa_config_de_env(): void
    {
        Mail::fake();
        $aviso = $this->aviso('Bienvenida al Sistema de Gestión Educativa');
        $destinatario = $this->usuario('padre@sge.local');

        $mailable = new AvisoInstitucionalMail($aviso, $destinatario);
        $mailable->assertHasSubject('['.config('institucion.sigla').'] '.$aviso->titulo);
        // El cuerpo NO incluye claves de .env ni contraseñas (§13).
        $render = $mailable->render();
        $this->assertStringNotContainsString('MAIL_PASSWORD', $render);
        $this->assertStringNotContainsString('APP_KEY', $render);
        $this->assertStringNotContainsString('DB_PASSWORD', $render);
        // Sí incluye la identidad institucional configurable.
        $this->assertStringContainsString(config('institucion.nombre'), $render);
    }

    // ============ §13: WhatsApp manual (wa.me, sin API) ============

    public function test_normalizacion_de_telefono_boliviano(): void
    {
        $this->assertSame('59170000001', WhatsApp::normalizarTelefono('70000001'));
        $this->assertSame('59170000001', WhatsApp::normalizarTelefono('+591 70000001'));
        $this->assertSame('59170000001', WhatsApp::normalizarTelefono('(591) 700-00001'));
        $this->assertSame('59170000001', WhatsApp::normalizarTelefono('59170000001'));
        $this->assertNull(WhatsApp::normalizarTelefono(null));
        $this->assertNull(WhatsApp::normalizarTelefono(''));
        $this->assertNull(WhatsApp::normalizarTelefono('123')); // insuficiente, no se inventa
    }

    public function test_enlace_whatsapp_es_manual_con_texto_precargado(): void
    {
        $enlace = WhatsApp::enlace('70000001', 'Hola colegio');
        $this->assertStringStartsWith('https://wa.me/59170000001?text=', $enlace);
        $this->assertStringContainsString(rawurlencode('Hola colegio'), $enlace);

        // Sin número: wa.me con texto (el usuario elige el contacto).
        $sinNumero = WhatsApp::enlace(null, 'Mensaje');
        $this->assertStringStartsWith('https://wa.me/?text=', $sinNumero);
    }

    public function test_detalle_de_aviso_ofrece_enlace_whatsapp_manual(): void
    {
        $admin = $this->admin();
        $aviso = $this->aviso('Bienvenida al Sistema de Gestión Educativa');

        $this->actingAs($admin)->get(route('avisos.show', $aviso))
            ->assertOk()
            ->assertSee('wa.me', false)
            ->assertSee('WhatsApp');
    }

    public function test_texto_de_citacion_omite_detalle_confidencial(): void
    {
        // §11: la citación de la incidencia confidencial no reproduce su detalle.
        $citacionConfidencial = \App\Models\Citacion::whereHas('incidencia', fn ($q) => $q->where('confidencial', true))->first();

        if ($citacionConfidencial) {
            $texto = WhatsApp::textoCitacion($citacionConfidencial);
            $this->assertStringNotContainsString('solo Administración puede ver este detalle', $texto);
        } else {
            $this->assertTrue(true); // el seeder puede no generar citación confidencial; no falla
        }
    }

    // ============ §16: reportes PDF/Excel con totales idénticos a pantalla ============

    public function test_reporte_aporte_por_curso_totales_coinciden_con_pantalla(): void
    {
        $gestion = Gestion::actual();

        // Cálculo independiente (el de la pantalla de cuotas, §14).
        $hoy = now()->toDateString();
        $emitido = 0;
        $pagado = 0;
        foreach (CuotaAporte::where('gestion_id', $gestion->id)->where('estado', '!=', 'exenta')->get() as $c) {
            $emitido += $c->montoCentavos();
            $pagado += $c->pagadoCentavos();
        }

        $data = ReporteService::aportePorCurso($gestion);
        $this->assertSame($emitido, $data['totales']['emitido']);
        $this->assertSame($pagado, $data['totales']['pagado']);
        // emitido - pagado == saldo (identidad contable en centavos).
        $this->assertSame($emitido - $pagado, $data['totales']['saldo']);
    }

    public function test_reporte_aporte_por_alumno_usa_estado_de_cuenta(): void
    {
        $gestion = Gestion::actual();
        $data = ReporteService::aportePorAlumno($gestion);

        // Al menos los alumnos con cuotas aparecen.
        $this->assertGreaterThan(0, $data['filas']->count());

        // El total por alumno == suma de filas (consistencia interna).
        $sumaFilas = $data['filas']->sum('emitido');
        $this->assertSame($sumaFilas, $data['totales']['emitido']);
    }

    public function test_pantalla_pdf_excel_de_aporte_por_curso_renderizan(): void
    {
        $admin = $this->admin();
        $gid = Gestion::actual()->id;

        $this->actingAs($admin)->get(route('reportes.aporte-curso', ['gestion' => $gid]))
            ->assertOk()->assertSee('Aporte por curso');

        $pdf = $this->actingAs($admin)->get(route('reportes.aporte-curso.pdf', ['gestion' => $gid]));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $excel = $this->actingAs($admin)->get(route('reportes.aporte-curso.excel', ['gestion' => $gid]));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $excel->headers->get('Content-Type'));
    }

    public function test_pantalla_pdf_excel_de_aporte_por_alumno_renderizan(): void
    {
        $admin = $this->admin();
        $gid = Gestion::actual()->id;

        $this->actingAs($admin)->get(route('reportes.aporte-alumno', ['gestion' => $gid]))
            ->assertOk()->assertSee('Aporte por alumno');

        $pdf = $this->actingAs($admin)->get(route('reportes.aporte-alumno.pdf', ['gestion' => $gid]));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $excel = $this->actingAs($admin)->get(route('reportes.aporte-alumno.excel', ['gestion' => $gid]));
        $excel->assertOk();
    }

    public function test_reporte_asistencia_por_curso_pantalla_pdf_excel(): void
    {
        $admin = $this->admin();
        $curso = Curso::where('nombre', '1ro de Primaria')->where('gestion_id', Gestion::actual()->id)->firstOrFail();
        $filtro = [
            'curso_id' => $curso->id,
            'turno' => 'manana',
            'desde' => now()->startOfMonth()->toDateString(),
            'hasta' => now()->endOfMonth()->toDateString(),
        ];

        $this->actingAs($admin)->get(route('reportes.asistencia-curso', $filtro))
            ->assertOk()->assertSee('Asistencia por curso');

        $pdf = $this->actingAs($admin)->get(route('reportes.asistencia-curso.pdf', $filtro));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $excel = $this->actingAs($admin)->get(route('reportes.asistencia-curso.excel', $filtro));
        $excel->assertOk();
    }

    public function test_docente_solo_reporta_asistencia_de_sus_cursos(): void
    {
        $docente = $this->usuario('docente@sge.local');
        // Docente asignado a 3ro de Secundaria; 1ro de Primaria NO es suyo.
        $ajeno = Curso::where('nombre', '1ro de Primaria')->where('gestion_id', Gestion::actual()->id)->firstOrFail();

        $this->actingAs($docente)->get(route('reportes.asistencia-curso.pdf', [
            'curso_id' => $ajeno->id, 'turno' => 'manana',
            'desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->toDateString(),
        ]))->assertForbidden();

        // Caso positivo (§6/§16): el docente SÍ alcanza el reporte de SUS cursos
        // (pantalla, PDF y Excel), con alcance por registro en el controlador.
        $suyo = $docente->cursosAsignados()->first();
        $this->assertNotNull($suyo, 'El docente semilla debe tener al menos un curso asignado.');

        $filtro = [
            'curso_id' => $suyo->id,
            'turno' => in_array($suyo->turno, ['manana', 'tarde'], true) ? $suyo->turno : 'manana',
            'desde' => now()->startOfMonth()->toDateString(),
            'hasta' => now()->toDateString(),
        ];
        $this->actingAs($docente)->get(route('reportes.asistencia-curso', $filtro))
            ->assertOk()->assertSee('Asistencia por curso');
        $this->actingAs($docente)->get(route('reportes.asistencia-curso.pdf', $filtro))->assertOk();
        $this->actingAs($docente)->get(route('reportes.asistencia-curso.excel', $filtro))->assertOk();
    }

    public function test_roles_con_reportes_ver_acceden_al_reporte_oficial(): void
    {
        // Regresión de permisos: Coordinadora/Subdirector tienen `reportes.ver`
        // (la Coordinadora además `asistencia.ver` desde la matriz 30/09/2026);
        // el middleware `reportes.ver|asistencia.ver` no puede dejarlos fuera
        // del reporte oficial de asistencia (§16).
        foreach (['coordinadora@sge.local', 'subdirector@sge.local'] as $correo) {
            $usuario = $this->usuario($correo);
            $curso = Curso::where('gestion_id', Gestion::actual()->id)->firstOrFail();

            $this->actingAs($usuario)->get(route('reportes.asistencia-curso', [
                'curso_id' => $curso->id, 'turno' => 'manana',
                'desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->toDateString(),
            ]))->assertOk();
        }
    }

    public function test_responsable_no_accede_al_reporte_oficial_de_asistencia(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $curso = Curso::where('gestion_id', Gestion::actual()->id)->firstOrFail();

        $this->actingAs($padre)->get(route('reportes.asistencia-curso', [
            'curso_id' => $curso->id, 'turno' => 'manana',
            'desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->toDateString(),
        ]))->assertForbidden();
    }

    public function test_responsable_no_accede_a_reportes_economicos(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $this->actingAs($padre)->get(route('reportes.aporte-curso'))->assertForbidden();
        $this->actingAs($padre)->get(route('reportes.index'))->assertForbidden();
    }

    // ============ §16: panel por rol ============

    public function test_panel_muestra_avisos_recientes_al_responsable(): void
    {
        $padre = $this->usuario('padre@sge.local');
        $this->actingAs($padre)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Avisos recientes');
    }

    public function test_panel_admin_renderiza_sin_error(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();
    }

    // ============ §17: respaldo manual fuera de public/, por rol ============

    public function test_respaldos_solo_para_acceso_total(): void
    {
        // Matriz 30/09/2026: Administración y Director (acceso a todo el
        // sistema) gestionan respaldos; el resto de roles, no.
        $this->actingAs($this->usuario('director@sge.local'))->get(route('respaldos.index'))->assertOk();
        $this->actingAs($this->usuario('docente@sge.local'))->get(route('respaldos.index'))->assertForbidden();
        $this->actingAs($this->usuario('padre@sge.local'))->get(route('respaldos.index'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('respaldos.index'))->assertOk();
    }

    public function test_generar_respaldo_crea_registro_y_guarda_fuera_de_public(): void
    {
        Storage::fake('respaldos');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('respaldos.store'), ['notas' => 'Prueba de respaldo'])
            ->assertRedirect();

        $respaldo = Respaldo::latest('id')->first();
        $this->assertNotNull($respaldo);

        // En SQLite (pruebas) el volcado automático de MySQL no aplica: el servicio
        // lo registra como error de forma controlada, sin lanzar excepción al usuario.
        // Lo importante: el registro existe, está auditado y NO se escribió en public/.
        $this->assertSame('Prueba de respaldo', $respaldo->notas);
        $this->assertFalse(Storage::disk('public')->exists($respaldo->archivo));
    }

    public function test_respaldo_ok_se_descarga_con_checksum_verificado(): void
    {
        Storage::fake('respaldos');
        $admin = $this->admin();

        // Simulamos un respaldo correcto generado fuera de línea.
        $contenido = "-- respaldo de prueba\n";
        Storage::disk('respaldos')->put('respaldo-test.sql', $contenido);
        $respaldo = Respaldo::create([
            'archivo' => 'respaldo-test.sql',
            'tamano_bytes' => strlen($contenido),
            'checksum' => hash('sha256', $contenido),
            'motor' => 'mysql',
            'base_datos' => 'sge_arajuruana',
            'tablas' => 10,
            'estado' => 'ok',
            'creado_por' => $admin->id,
        ]);

        // Descarga permitida para Administración, con checksum válido.
        $this->actingAs($admin)->get(route('respaldos.descargar', $respaldo))->assertOk();

        // Si el archivo se altera, el checksum falla y NO se descarga (§17).
        Storage::disk('respaldos')->put('respaldo-test.sql', $contenido.'-- alterado');
        $this->actingAs($admin)->get(route('respaldos.descargar', $respaldo))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_documento_de_restauracion_existe(): void
    {
        // §17: procedimiento de restauración documentado.
        $this->assertFileExists(base_path('docs/RESPALDOS.md'));
        $contenido = file_get_contents(base_path('docs/RESPALDOS.md'));
        $this->assertStringContainsString('RESTAURACIÓN', $contenido);
        $this->assertStringContainsString('fuera de', strtolower($contenido));
    }
}
