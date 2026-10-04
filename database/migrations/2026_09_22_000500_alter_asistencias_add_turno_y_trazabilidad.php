<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía la tabla asistencias con turno, inscripción, curso y trazabilidad.
 *
 * Al principio la asistencia se tomaba una vez por día. Como hay cursos que
 * pasan clases en la mañana y en la tarde, ahora se registra por turno, se
 * vincula con la inscripción y el curso del alumno y se guarda quién hizo una
 * corrección. Por eso también cambiamos el índice único: de (alumno, fecha) a
 * (alumno, fecha, turno).
 */
return new class extends Migration
{
    /**
     * Aplica los cambios en tres pasos, en un orden pensado para que MySQL
     * no se queje de las claves foráneas.
     */
    public function up(): void
    {
        // Asistencia por curso y turno. "Sin registro" no equivale a "ausente":
        // el estado solo existe cuando alguien lo registra.

        // 1) Primero agregamos las columnas nuevas.
        Schema::table('asistencias', function (Blueprint $table) {
            // Turno de la asistencia; los registros antiguos quedan como "manana".
            $table->string('turno', 20)->default('manana')->after('fecha');
            $table->foreignId('inscripcion_id')->nullable()->after('estudiante_id')->constrained('inscripciones')->nullOnDelete();
            $table->foreignId('curso_id')->nullable()->after('inscripcion_id')->constrained('cursos')->nullOnDelete();
            // Trazabilidad de las correcciones autorizadas: quién modificó el registro.
            $table->foreignId('modificado_por')->nullable()->after('registrado_por')->constrained('users')->nullOnDelete();
        });

        // 2) Creamos el nuevo índice único ANTES de quitar el anterior:
        //    en MySQL la clave foránea de estudiante_id necesita un índice que
        //    empiece por esa columna, y (estudiante_id, fecha, turno) la cubre.
        Schema::table('asistencias', function (Blueprint $table) {
            // Evita registros duplicados para el mismo alumno, fecha y turno.
            $table->unique(['estudiante_id', 'fecha', 'turno']);
            // Índice para armar rápido la lista de asistencia de un curso.
            $table->index(['curso_id', 'fecha', 'turno']);
        });

        // 3) Recién ahora se puede eliminar el índice único viejo.
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropUnique(['estudiante_id', 'fecha']);
        });
    }

    /**
     * Revierte los cambios en el orden contrario.
     */
    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            // Restauramos primero el índice viejo, que también cubre la clave foránea de estudiante_id.
            $table->unique(['estudiante_id', 'fecha']);
        });

        // Después quitamos los índices nuevos, las claves foráneas y el turno.
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropUnique(['estudiante_id', 'fecha', 'turno']);
            $table->dropIndex(['curso_id', 'fecha', 'turno']);
            $table->dropConstrainedForeignId('modificado_por');
            $table->dropConstrainedForeignId('curso_id');
            $table->dropConstrainedForeignId('inscripcion_id');
            $table->dropColumn('turno');
        });
    }
};
