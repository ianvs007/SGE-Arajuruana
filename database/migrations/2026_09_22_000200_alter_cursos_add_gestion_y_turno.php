<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciona los cursos con una gestión y les agrega grado, turno y orden.
 *
 * Así cada gestión tiene su propia lista de cursos (el "3ro de Secundaria" de
 * 2025 es un registro distinto del de 2026) y podemos saber en qué turno pasa
 * clases cada curso, algo necesario para registrar la asistencia por turno.
 */
return new class extends Migration
{
    /**
     * Agrega las columnas nuevas a la tabla cursos.
     */
    public function up(): void
    {
        // El curso pasa a depender de una gestión y admite un turno configurable.
        // anio_lectivo se conserva por compatibilidad mientras dura la transición.
        Schema::table('cursos', function (Blueprint $table) {
            // Gestión a la que pertenece el curso; si se borra la gestión, el curso queda sin ella.
            $table->foreignId('gestion_id')->nullable()->after('id')->constrained('gestiones')->nullOnDelete();
            $table->string('grado', 60)->nullable()->after('nivel');   // p. ej. "1ro", "3ro"
            $table->string('turno', 20)->nullable()->after('paralelo'); // manana, tarde, mixto
            $table->unsignedSmallInteger('orden')->nullable()->after('turno'); // para mostrar los cursos ordenados
        });
    }

    /**
     * Revierte la migración: quita la clave foránea con su columna y luego las
     * demás columnas agregadas.
     */
    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gestion_id');
            $table->dropColumn(['grado', 'turno', 'orden']);
        });
    }
};
