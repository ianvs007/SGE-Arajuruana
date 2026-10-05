<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InscripcionesImportacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function administracion(): User
    {
        return User::where('email', 'administracion@sge.local')->firstOrFail();
    }

    // ---------- Inscripciones (punto 7) ----------

    public function test_inscripcion_unica_por_gestion_y_sincroniza_curso_actual(): void
    {
        $admin = $this->administracion();
        $gestion = Gestion::actual();
        // Alumno nuevo sin inscripción en la gestión actual (el seeder ya
        // inscribió a los tres alumnos demo).
        $estudiante = Estudiante::create([
            'codigo' => 'EST-2026-500',
            'nombres' => 'Rosa',
            'apellidos' => 'Vaca Flores',
            'estado' => 'activo',
        ]);
        $curso = Curso::where('gestion_id', $gestion->id)->firstOrFail();

        $this->actingAs($admin)->post(route('inscripciones.store'), [
            'gestion_id' => $gestion->id,
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'estado' => 'activa',
            'fecha_inscripcion' => '2026-02-02',
        ])->assertRedirect(route('inscripciones.index', ['gestion_id' => $gestion->id]));

        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => $estudiante->id,
            'gestion_id' => $gestion->id,
            'curso_id' => $curso->id,
        ]);

        // curso_id del estudiante sincronizado con la gestión actual (punto 7).
        $this->assertSame($curso->id, $estudiante->fresh()->curso_id);
    }

    public function test_no_permite_doble_inscripcion_en_la_misma_gestion(): void
    {
        $admin = $this->administracion();
        $inscripcion = Inscripcion::firstOrFail();
        $curso = Curso::where('gestion_id', $inscripcion->gestion_id)->firstOrFail();

        $response = $this->actingAs($admin)->from(route('inscripciones.create', ['gestion_id' => $inscripcion->gestion_id]))
            ->post(route('inscripciones.store'), [
                'gestion_id' => $inscripcion->gestion_id,
                'estudiante_id' => $inscripcion->estudiante_id,
                'curso_id' => $curso->id,
                'estado' => 'activa',
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, Inscripcion::where('estudiante_id', $inscripcion->estudiante_id)
            ->where('gestion_id', $inscripcion->gestion_id)->count());
    }

    public function test_valida_que_el_curso_pertenezca_a_la_gestion(): void
    {
        $admin = $this->administracion();
        $gestionVieja = Gestion::where('es_actual', false)->firstOrFail();
        $cursoActual = Curso::where('gestion_id', Gestion::actual()->id)->firstOrFail();
        $estudiante = Estudiante::firstOrFail();

        $this->actingAs($admin)->post(route('inscripciones.store'), [
            'gestion_id' => $gestionVieja->id,
            'estudiante_id' => $estudiante->id,
            'curso_id' => $cursoActual->id, // curso de otra gestión
            'estado' => 'activa',
        ])->assertSessionHasErrors('curso_id');
    }

    public function test_cancelar_inscripcion_conserva_historial(): void
    {
        $admin = $this->administracion();
        $inscripcion = Inscripcion::where('estado', 'activa')->firstOrFail();

        $this->actingAs($admin)->delete(route('inscripciones.destroy', $inscripcion))
            ->assertRedirect();

        $this->assertSame('cancelada', $inscripcion->fresh()->estado);
        $this->assertDatabaseHas('inscripciones', ['id' => $inscripcion->id]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'inscripciones.cancelar']);
    }

    public function test_docente_no_accede_a_inscripciones(): void
    {
        $docente = User::where('email', 'docente@sge.local')->firstOrFail();

        $this->actingAs($docente)->get(route('inscripciones.index'))->assertForbidden();
    }

    // ---------- Importación Excel/CSV (punto 8) ----------

    public function test_plantilla_descargable(): void
    {
        $this->actingAs($this->administracion())
            ->get(route('importacion.plantilla'))
            ->assertOk();
    }

    public function test_importacion_csv_con_previsualizacion_y_confirmacion(): void
    {
        $admin = $this->administracion();
        $gestion = Gestion::actual();
        $curso = Curso::where('gestion_id', $gestion->id)->firstOrFail();

        $csv = implode("\n", [
            'codigo,nombres,apellidos,documento,fecha_nacimiento,sexo',
            'EST-2026-900,Carmen Rosa,Flores Mamani,0011223,2013-05-05,Femenino',
            'EST-2026-901,Luis Fernando,Choque Apaza,0044556,2012-11-30,Masculino',
        ]);
        $archivo = UploadedFile::fake()->createWithContent('alumnos.csv', $csv);

        // Paso 1: previsualización.
        $this->actingAs($admin)->post(route('importacion.previsualizar'), [
            'archivo' => $archivo,
            'gestion_id' => $gestion->id,
            'curso_id' => $curso->id,
        ])->assertRedirect(route('importacion.preview'));

        $this->actingAs($admin)->get(route('importacion.preview'))
            ->assertOk()
            ->assertSee('EST-2026-900')
            ->assertSee('Aceptadas: 2');

        // Todavía no se creó nada (punto 8: nada se importa sin confirmación).
        $this->assertDatabaseMissing('estudiantes', ['codigo' => 'EST-2026-900']);

        // Paso 2: confirmación transaccional.
        $this->actingAs($admin)->post(route('importacion.confirmar'))
            ->assertRedirect(route('importacion.index'));

        $this->assertDatabaseHas('estudiantes', ['codigo' => 'EST-2026-900', 'documento' => '0011223']);
        $this->assertDatabaseHas('estudiantes', ['codigo' => 'EST-2026-901']);

        // Documento conservó el cero inicial (texto, punto 8).
        $this->assertSame('0011223', Estudiante::where('codigo', 'EST-2026-900')->value('documento'));

        // Inscripción automática en el curso de contexto.
        $estudiante = Estudiante::where('codigo', 'EST-2026-900')->firstOrFail();
        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => $estudiante->id,
            'gestion_id' => $gestion->id,
            'curso_id' => $curso->id,
        ]);

        $this->assertDatabaseHas('auditoria', ['accion' => 'importacion.estudiantes']);
    }

    public function test_importacion_detecta_duplicados_por_documento_y_codigo(): void
    {
        $admin = $this->administracion();
        $gestion = Gestion::actual();
        // Los alumnos demo no tienen documento; se prepara uno con documento.
        $existente = Estudiante::firstOrFail();
        $existente->update(['documento' => '8888888']);

        $csv = implode("\n", [
            'codigo,nombres,apellidos,documento,fecha_nacimiento,sexo',
            // código duplicado contra BD
            $existente->codigo.',Otra,Persona,9999999,2013-01-01,Femenino',
            // documento duplicado contra BD
            "EST-2026-902,Nueva,Apellido,{$existente->documento},2013-01-01,Masculino",
            // código duplicado dentro del archivo
            'EST-2026-903,Primera,Gonzales,7000001,2013-01-01,Femenino',
            'EST-2026-903,Segunda,Rojas,7000002,2013-01-01,Femenino',
            // fila válida
            'EST-2026-904,Valida,Fernandez,7000003,2013-01-01,Femenino',
        ]);
        $archivo = UploadedFile::fake()->createWithContent('alumnos.csv', $csv);

        $this->actingAs($admin)->post(route('importacion.previsualizar'), [
            'archivo' => $archivo,
            'gestion_id' => $gestion->id,
        ])->assertRedirect(route('importacion.preview'));

        $this->actingAs($admin)->get(route('importacion.preview'))
            ->assertOk()
            ->assertSee('El código ya existe en el sistema.')
            ->assertSee('Ya existe un alumno con este documento en el sistema.')
            ->assertSee('Código duplicado dentro del archivo')
            ->assertSee('Aceptadas: 2')   // EST-2026-903 (primera) y EST-2026-904
            ->assertSee('Rechazadas: 3'); // código existente, documento existente, 903 repetido

        // Confirmar: solo las filas válidas se importan.
        $this->actingAs($admin)->post(route('importacion.confirmar'))
            ->assertRedirect(route('importacion.index'));

        $this->assertDatabaseHas('estudiantes', ['codigo' => 'EST-2026-904']);
        $this->assertDatabaseMissing('estudiantes', ['codigo' => 'EST-2026-902']);
        // El código duplicado dentro del archivo se importó una sola vez.
        $this->assertSame(1, Estudiante::where('codigo', 'EST-2026-903')->count());
    }

    public function test_importacion_advierte_posible_duplicado_sin_documento(): void
    {
        $admin = $this->administracion();
        $gestion = Gestion::actual();
        $existente = Estudiante::firstOrFail();

        $csv = implode("\n", [
            'codigo,nombres,apellidos,documento,fecha_nacimiento,sexo',
            // Mismo nombre+fecha que un existente, sin documento → advertencia, no rechazo.
            "EST-2026-910,{$existente->nombres},{$existente->apellidos},,{$existente->fecha_nacimiento?->format('Y-m-d')},",
        ]);
        $archivo = UploadedFile::fake()->createWithContent('alumnos.csv', $csv);

        $this->actingAs($admin)->post(route('importacion.previsualizar'), [
            'archivo' => $archivo,
            'gestion_id' => $gestion->id,
        ])->assertRedirect(route('importacion.preview'));

        $this->actingAs($admin)->get(route('importacion.preview'))
            ->assertOk()
            ->assertSee('Posible duplicado sin documento');

        // Sin marcar la opción, no se importa la fila advertida.
        $this->actingAs($admin)->post(route('importacion.confirmar'))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('estudiantes', ['codigo' => 'EST-2026-910']);
    }

    public function test_docente_no_puede_importar(): void
    {
        $docente = User::where('email', 'docente@sge.local')->firstOrFail();

        $this->actingAs($docente)->get(route('importacion.index'))->assertForbidden();
        $this->actingAs($docente)->get(route('importacion.plantilla'))->assertForbidden();
    }

    public function test_importacion_rechaza_archivo_invalido(): void
    {
        $admin = $this->administracion();

        $this->actingAs($admin)->from(route('importacion.index'))
            ->post(route('importacion.previsualizar'), [
                'archivo' => UploadedFile::fake()->create('virus.exe', 10),
                'gestion_id' => Gestion::actual()->id,
            ])
            ->assertRedirect(route('importacion.index'))
            ->assertSessionHasErrors('archivo');
    }
}
