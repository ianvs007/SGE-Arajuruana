<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asistencia por curso y turno (§9). "Sin registro" no equivale a
        // "ausente": el estado se crea solo cuando Administración registra.

        // 1) Primero las columnas nuevas.
        Schema::table('asistencias', function (Blueprint $table) {
            $table->string('turno', 20)->default('manana')->after('fecha');
            $table->foreignId('inscripcion_id')->nullable()->after('estudiante_id')->constrained('inscripciones')->nullOnDelete();
            $table->foreignId('curso_id')->nullable()->after('inscripcion_id')->constrained('cursos')->nullOnDelete();
            // Trazabilidad de correcciones autorizadas (§9)
            $table->foreignId('modificado_por')->nullable()->after('registrado_por')->constrained('users')->nullOnDelete();
        });

        // 2) Crear el nuevo índice único ANTES de quitar el anterior:
        //    en MySQL la FK de estudiante_id necesita un índice que empiece
        //    por esa columna, y (estudiante_id, fecha, turno) la cubre.
        Schema::table('asistencias', function (Blueprint $table) {
            // Evita registros duplicados para alumno, fecha y turno (§9).
            $table->unique(['estudiante_id', 'fecha', 'turno']);
            $table->index(['curso_id', 'fecha', 'turno']);
        });

        // 3) Ahora sí se puede soltar el índice único viejo.
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropUnique(['estudiante_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            // Restaurar el índice viejo primero (cubre la FK de estudiante_id).
            $table->unique(['estudiante_id', 'fecha']);
        });

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
