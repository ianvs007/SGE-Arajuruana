<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 5 (§13, §16, §17): comunicaciones, reportes y respaldo.
 *
 * §13 — Comunicaciones:
 * 1. `avisos` se amplía con destinatarios ESPECÍFICOS (curso o un alumno) y con
 *    confirmación de lectura OPCIONAL (`requiere_confirmacion`), que nunca
 *    bloquea el uso del sistema.
 * 2. `aviso_destinatarios` materializa a QUIÉN se dirigió el aviso al publicarlo
 *    (trazabilidad: si después cambia la inscripción o el responsable, queda el
 *    registro de a quién se le avisó) y guarda lectura/confirmación/envío de
 *    correo por destinatario.
 * 3. `respaldos` (§17): registro del respaldo manual. El archivo físico se guarda
 *    FUERA de `public/` (en `storage/app/privado/respaldos`), nunca accesible por
 *    URL; la descarga pasa por el controlador con permiso `respaldos.gestionar`.
 *
 * No se tocan las migraciones anteriores: solo se agregan columnas y tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- 1) Avisos con destinatarios específicos (§13) ----------
        Schema::table('avisos', function (Blueprint $table) {
            // audiencia: todos|padres|docentes|administrativos|curso|familia
            // - curso  → responsables de los alumnos del curso + docentes del curso
            // - familia→ responsables de UN alumno (aviso dirigido, p. ej. citación)
            $table->foreignId('curso_id')->nullable()->after('audiencia');
            $table->foreignId('estudiante_id')->nullable()->after('curso_id');
            // Confirmación de lectura OPCIONAL y NO BLOQUEANTE (§13): el sistema
            // sigue usable sin confirmar; solo se registra quién confirmó.
            $table->boolean('requiere_confirmacion')->default(false)->after('estudiante_id');
            $table->timestamp('confirmar_antes')->nullable()->after('requiere_confirmacion');
            $table->timestamp('enviado_en')->nullable()->after('publicado_en');
        });

        Schema::table('avisos', function (Blueprint $table) {
            $table->foreign('curso_id')->references('id')->on('cursos')->nullOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('estudiantes')->nullOnDelete();
            $table->index(['publicado', 'audiencia']);
        });

        // ---------- 2) Destinatarios materializados + lectura (§13) ----------
        Schema::create('aviso_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Por qué es destinatario: auditoría legible del alcance del aviso.
            $table->string('motivo', 180);
            $table->timestamp('leido_en')->nullable();
            $table->timestamp('confirmado_en')->nullable();
            // Correo: intento registrado (éxito o fallo) — el envío es opcional y
            // su fallo NO impide que el aviso exista ni se vea en el sistema.
            $table->timestamp('correo_enviado_en')->nullable();
            $table->string('correo_estado', 20)->nullable(); // enviado|error|omitido
            $table->string('correo_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['aviso_id', 'user_id'], 'destinatario_unico_por_aviso');
            $table->index(['user_id', 'leido_en']);
        });

        // ---------- 3) Registro de respaldos manuales (§17) ----------
        Schema::create('respaldos', function (Blueprint $table) {
            $table->id();
            $table->string('archivo', 255);          // ruta relativa a storage/app/privado
            $table->unsignedBigInteger('tamano_bytes')->default(0);
            $table->string('checksum', 64)->nullable(); // sha256 del archivo
            $table->string('motor', 20);             // mysql
            $table->string('base_datos', 100);
            $table->unsignedSmallInteger('tablas')->default(0);
            $table->boolean('incluye_env')->default(false);
            $table->string('estado', 20)->default('ok'); // ok|error
            $table->string('error', 500)->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respaldos');
        Schema::dropIfExists('aviso_destinatarios');

        Schema::table('avisos', function (Blueprint $table) {
            $table->dropIndex(['publicado', 'audiencia']);
            $table->dropConstrainedForeignId('curso_id');
            $table->dropConstrainedForeignId('estudiante_id');
            $table->dropColumn([
                'requiere_confirmacion', 'confirmar_antes', 'enviado_en',
            ]);
        });
    }
};
