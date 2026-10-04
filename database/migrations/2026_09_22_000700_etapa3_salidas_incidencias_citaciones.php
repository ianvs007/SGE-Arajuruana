<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 3: salidas, horarios, incidencias y citaciones.
 *
 * En esta etapa ampliamos varias tablas de la vida escolar:
 * - salidas_estudiantes: la salida pasa a tener un flujo con estados
 *   (autorizada, salida efectiva, retornada o cancelada) y se registra quién
 *   hizo cada paso y cuándo.
 * - horarios_curso: se agrega la fecha de fin de vigencia del horario.
 * - incidencias_categorias (nueva) e incidencias: categorías configurables y
 *   marca de confidencialidad.
 * - citaciones: acuerdos, responsable del seguimiento, fecha de revisión e
 *   incidencia relacionada.
 */
return new class extends Migration
{
    /**
     * Aplica los cambios de la etapa 3.
     */
    public function up(): void
    {
        // ---------- Salidas con flujo autorizada → salida efectiva → retorno ----------
        Schema::table('salidas_estudiantes', function (Blueprint $table) {
            // La hora efectiva y quién retira se registran después, porque
            // autorizar una salida no es lo mismo que la salida efectiva.
            $table->time('hora_salida')->nullable()->change();
            $table->string('responsable_retiro')->nullable()->change();

            $table->string('estado', 30)->default('autorizada')->after('fecha');
            // autorizada | salida_efectiva | retornada | cancelada

            // Paso 1: quién autorizó la salida y cuándo.
            $table->foreignId('autorizado_por')->nullable()->after('motivo')->constrained('users')->nullOnDelete();
            $table->timestamp('autorizado_en')->nullable()->after('autorizado_por');

            // Paso 2: momento real en que el alumno salió y quién lo registró.
            $table->timestamp('salida_en')->nullable()->after('hora_salida');
            $table->foreignId('salida_registrado_por')->nullable()->after('salida_en')->constrained('users')->nullOnDelete();

            // Paso 3: retorno del alumno al colegio y quién lo registró.
            $table->time('hora_retorno')->nullable()->after('salida_registrado_por');
            $table->timestamp('retorno_en')->nullable()->after('hora_retorno');
            $table->foreignId('retorno_registrado_por')->nullable()->after('retorno_en')->constrained('users')->nullOnDelete();

            // Verificación manual registrada (decisión confirmada con el colegio):
            // quién revisó el documento de la persona que retira al alumno. Es
            // solo una constancia escrita, sin validación automática.
            $table->string('verificacion_retiro', 255)->nullable()->after('documento_responsable');

            // Índice para consultar rápido las salidas de un alumno según su estado.
            $table->index(['estudiante_id', 'estado']);
        });

        // ---------- Vigencia de horarios ----------
        // Modificar o desactivar un horario NO debe cambiar la interpretación de
        // asistencias pasadas: la fecha de fin de vigencia conserva el
        // significado histórico de cada horario.
        Schema::table('horarios_curso', function (Blueprint $table) {
            $table->timestamp('vigente_hasta')->nullable()->after('vigente_desde');
        });

        // ---------- Categorías configurables de incidencias ----------
        Schema::create('incidencias_categorias', function (Blueprint $table) {
            $table->id();
            // Nombre único de la categoría (por ejemplo "Convivencia y disciplina").
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            // Una categoría desactivada ya no se ofrece, pero las incidencias antiguas la conservan.
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('incidencias', function (Blueprint $table) {
            // Categoría de la incidencia; si se borra la categoría, la incidencia queda sin ella.
            $table->foreignId('categoria_id')->nullable()->after('tipo')->constrained('incidencias_categorias')->nullOnDelete();
            // Las incidencias confidenciales solo las ve Administración
            // (decisión confirmada, siguiendo el principio de mínimo privilegio).
            $table->boolean('confidencial')->default(false)->after('categoria_id');
            $table->index('confidencial');
        });

        // ---------- Citaciones con acuerdos, seguimiento e incidencia asociada ----------
        Schema::table('citaciones', function (Blueprint $table) {
            // Incidencia que motivó la citación, si la hay.
            $table->foreignId('incidencia_id')->nullable()->after('descripcion')->constrained('incidencias')->nullOnDelete();
            // Acuerdos alcanzados con la familia en la reunión.
            $table->text('acuerdos')->nullable()->after('observaciones_seguimiento');
            // Persona del colegio encargada de hacer el seguimiento de los acuerdos.
            $table->foreignId('seguimiento_responsable_id')->nullable()->after('acuerdos')->constrained('users')->nullOnDelete();
            // Fecha en que se revisará si se cumplieron los acuerdos (indexada para listar las próximas).
            $table->date('fecha_revision')->nullable()->after('seguimiento_responsable_id');
            $table->index('fecha_revision');
        });
    }

    /**
     * Revierte los cambios en orden inverso: primero las tablas que dependen
     * de otras (citaciones depende de incidencias) y al final las salidas.
     */
    public function down(): void
    {
        Schema::table('citaciones', function (Blueprint $table) {
            $table->dropIndex(['fecha_revision']);
            $table->dropConstrainedForeignId('seguimiento_responsable_id');
            $table->dropColumn(['acuerdos', 'fecha_revision']);
            $table->dropConstrainedForeignId('incidencia_id');
        });

        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropIndex(['confidencial']);
            $table->dropConstrainedForeignId('categoria_id');
            $table->dropColumn('confidencial');
        });

        Schema::dropIfExists('incidencias_categorias');

        Schema::table('horarios_curso', function (Blueprint $table) {
            $table->dropColumn('vigente_hasta');
        });

        Schema::table('salidas_estudiantes', function (Blueprint $table) {
            $table->dropIndex(['estudiante_id', 'estado']);
            $table->dropConstrainedForeignId('retorno_registrado_por');
            $table->dropConstrainedForeignId('salida_registrado_por');
            $table->dropConstrainedForeignId('autorizado_por');
            $table->dropColumn([
                'estado', 'autorizado_en', 'salida_en', 'hora_retorno', 'retorno_en',
                'verificacion_retiro',
            ]);
            // La hora de salida vuelve a ser obligatoria, como en la versión original.
            $table->time('hora_salida')->nullable(false)->change();
        });
    }
};
