<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 5: comunicaciones, reportes y respaldos.
 *
 * Comunicaciones:
 * 1. La tabla `avisos` se amplía para poder dirigir un aviso a destinatarios
 *    ESPECÍFICOS (un curso o la familia de un alumno) y para pedir una
 *    confirmación de lectura OPCIONAL (`requiere_confirmacion`), que nunca
 *    bloquea el uso del sistema.
 * 2. `aviso_destinatarios` guarda a QUIÉN se dirigió cada aviso en el momento de
 *    publicarlo. Así queda constancia aunque luego cambie la inscripción o el
 *    responsable del alumno, y se registra por cada destinatario si leyó,
 *    confirmó y si se le envió el correo.
 * 3. `respaldos`: registro de los respaldos manuales de la base de datos. El
 *    archivo se guarda FUERA de `public/` (en `storage/app/privado/respaldos`),
 *    así que nunca se puede abrir desde una URL; la descarga pasa por el
 *    controlador, que exige el permiso `respaldos.gestionar`.
 *
 * No modificamos migraciones anteriores: solo agregamos columnas y tablas.
 */
return new class extends Migration
{
    /**
     * Aplica los cambios de la etapa 5.
     */
    public function up(): void
    {
        // ---------- 1) Avisos con destinatarios específicos ----------
        Schema::table('avisos', function (Blueprint $table) {
            // Valores posibles de audiencia: todos|padres|docentes|administrativos|curso|familia
            // - curso   → responsables de los alumnos del curso + docentes del curso
            // - familia → responsables de UN alumno (aviso dirigido, p. ej. un recordatorio)
            $table->foreignId('curso_id')->nullable()->after('audiencia');
            $table->foreignId('estudiante_id')->nullable()->after('curso_id');
            // Confirmación de lectura OPCIONAL y NO BLOQUEANTE: el sistema sigue
            // funcionando aunque no se confirme; solo se registra quién confirmó.
            $table->boolean('requiere_confirmacion')->default(false)->after('estudiante_id');
            // Fecha límite sugerida para confirmar la lectura.
            $table->timestamp('confirmar_antes')->nullable()->after('requiere_confirmacion');
            // Última vez que se hizo el envío del aviso por correo a sus destinatarios.
            $table->timestamp('enviado_en')->nullable()->after('publicado_en');
        });

        // Claves foráneas e índice en un paso aparte, cuando las columnas ya existen.
        Schema::table('avisos', function (Blueprint $table) {
            $table->foreign('curso_id')->references('id')->on('cursos')->nullOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('estudiantes')->nullOnDelete();
            $table->index(['publicado', 'audiencia']);
        });

        // ---------- 2) Destinatarios guardados al publicar + lectura ----------
        Schema::create('aviso_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Por qué esta persona es destinataria: deja claro el alcance del aviso.
            $table->string('motivo', 180);
            // Cuándo lo leyó y cuándo confirmó la lectura (si se pidió).
            $table->timestamp('leido_en')->nullable();
            $table->timestamp('confirmado_en')->nullable();
            // Correo: se registra cada intento (con éxito o con error). El envío
            // es opcional y si falla NO impide que el aviso exista ni que se vea en el sistema.
            $table->timestamp('correo_enviado_en')->nullable();
            $table->string('correo_estado', 20)->nullable(); // enviado|error|omitido
            $table->string('correo_error', 500)->nullable();
            $table->timestamps();

            // Una persona figura una sola vez como destinataria de cada aviso.
            $table->unique(['aviso_id', 'user_id'], 'destinatario_unico_por_aviso');
            // Índice para mostrar rápido los avisos no leídos de un usuario.
            $table->index(['user_id', 'leido_en']);
        });

        // ---------- 3) Registro de respaldos manuales ----------
        Schema::create('respaldos', function (Blueprint $table) {
            $table->id();
            $table->string('archivo', 255);          // ruta relativa a storage/app/privado
            $table->unsignedBigInteger('tamano_bytes')->default(0);
            $table->string('checksum', 64)->nullable(); // sha256 del archivo, para comprobar que no se alteró
            $table->string('motor', 20);             // mysql
            $table->string('base_datos', 100);
            // Cantidad de tablas incluidas en el respaldo.
            $table->unsignedSmallInteger('tablas')->default(0);
            // Indica si el respaldo incluye también el archivo de configuración .env.
            $table->boolean('incluye_env')->default(false);
            $table->string('estado', 20)->default('ok'); // ok|error
            $table->string('error', 500)->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Índice para listar los respaldos por estado y fecha.
            $table->index(['estado', 'created_at']);
        });
    }

    /**
     * Revierte la etapa 5: borra las tablas nuevas y quita las columnas
     * agregadas a avisos.
     */
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
