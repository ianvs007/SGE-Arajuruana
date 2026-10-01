<?php

namespace Tests\Feature;

use App\Models\Gestion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Etapa 2 — Roles y permisos (§5, §6):
 * - Acceso denegado por defecto.
 * - Administración configura gestiones; otros roles no.
 * - Responsable familiar ve solo lo autorizado.
 */
class RolesPermisosTest extends TestCase
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

    public function test_las_rutas_restringidas_requieren_sesion(): void
    {
        foreach (['/dashboard', '/gestiones', '/cursos', '/estudiantes'] as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }
    }

    public function test_administracion_accede_a_configuracion_de_gestiones(): void
    {
        $this->actingAs($this->usuario('administracion@sge.local'))
            ->get('/gestiones')
            ->assertOk()
            ->assertSee('Gestión 2026');
    }

    public function test_director_tiene_acceso_a_todo_el_sistema(): void
    {
        // Matriz corregida el 30/09/2026: el Director accede a TODO el sistema,
        // incluida la configuración de gestiones y cursos.
        $this->actingAs($this->usuario('director@sge.local'))
            ->get('/gestiones')
            ->assertOk()
            ->assertSee('Gestión 2026');

        $this->actingAs($this->usuario('director@sge.local'))
            ->get('/cursos')
            ->assertOk();
    }

    public function test_docente_no_puede_configurar_gestiones_ni_cursos(): void
    {
        $docente = $this->usuario('docente@sge.local');

        $this->actingAs($docente)->get('/gestiones')->assertForbidden();
        $this->actingAs($docente)->get('/cursos')->assertForbidden();
    }

    public function test_responsable_familiar_no_accede_a_modulos_administrativos(): void
    {
        $padre = $this->usuario('padre@sge.local');

        foreach (['/gestiones', '/cursos', '/users', '/asistencias/registrar'] as $ruta) {
            $this->actingAs($padre)->get($ruta)->assertForbidden();
        }
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/register')->assertStatus(404);
    }

    public function test_marcar_gestion_actual_desmarca_las_demas(): void
    {
        $admin = $this->usuario('administracion@sge.local');
        $anterior = Gestion::where('anio', 2025)->firstOrFail();

        $this->actingAs($admin)
            ->post("/gestiones/{$anterior->id}/marcar-actual")
            ->assertRedirect();

        $this->assertTrue($anterior->fresh()->es_actual);
        $this->assertSame(1, Gestion::where('es_actual', true)->count());
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        // §6: activación e inactivación de cuentas.
        User::create([
            'name' => 'Inactivo Demo',
            'email' => 'inactivo@sge.local',
            'password' => Hash::make('password'),
            'activo' => false,
        ]);

        $this->post('/login', [
            'email' => 'inactivo@sge.local',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_responsable_no_accede_a_alumno_de_otra_familia(): void
    {
        // §20.2: alterar identificadores no permite consultar datos ajenos.
        $ajeno = \App\Models\Estudiante::create([
            'codigo' => 'EST-AJENO',
            'nombres' => 'Otra',
            'apellidos' => 'Familia',
            'estado' => 'activo',
        ]);

        $this->actingAs($this->usuario('padre@sge.local'))
            ->get("/estudiantes/{$ajeno->id}")
            ->assertForbidden();
    }

    public function test_docente_no_ve_alumnos_fuera_de_sus_cursos(): void
    {
        // §20.9: el docente no obtiene acceso a datos privados de otros cursos.
        $docente = $this->usuario('docente@sge.local'); // asignado solo a 3ro Secundaria

        $dePrimaria = \App\Models\Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();
        $deSecundaria = \App\Models\Estudiante::where('codigo', 'EST-2026-002')->firstOrFail();

        $this->actingAs($docente)->get('/estudiantes')->assertOk()
            ->assertSee('José Luis')
            ->assertDontSee('María Fernanda');

        $this->actingAs($docente)->get("/estudiantes/{$dePrimaria->id}")->assertForbidden();
        $this->actingAs($docente)->get("/estudiantes/{$deSecundaria->id}")->assertOk();
    }

    public function test_padre_y_madre_no_duplican_alumno_vinculado(): void
    {
        // §20.3: cuentas distintas vinculadas al mismo alumno sin duplicar el vínculo.
        $padre = $this->usuario('padre@sge.local');
        $madre = $this->usuario('madre@sge.local');

        $este = \App\Models\Estudiante::where('codigo', 'EST-2026-001')->firstOrFail();

        $this->assertTrue($padre->representaA($este));
        $this->assertTrue($madre->representaA($este));
        $this->assertSame(1, $este->responsables()->whereKey([$padre->id, $madre->id])->distinct()->count('estudiante_id'));
    }
}
