<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea las tablas de asignación docente, horarios y calendario.
 *
 * - docente_curso: qué docente está asignado a qué curso en cada gestión.
 * - horarios_curso: los días y turnos en que pasa clases cada curso.
 * - calendario_excepciones: días en que no hay clases (feriados, actividades
 *   internas, etc.).
 *
 * Con estas tres tablas el sistema sabe qué cursos puede atender cada docente
 * y en qué días corresponde registrar asistencia, de modo que un día sin
 * clases no aparezca como falta de los alumnos.
 */
return new class extends Migration
{
    /**
     * Crea las tres tablas.
     */
    public function up(): void
    {
        // Asignación de docentes a cursos por gestión: los docentes solo pueden
        // trabajar sobre los cursos que tienen asignados.
        Schema::create('docente_curso', function (Blueprint $table) {
            $table->id();
            // Usuario con rol Docente.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            $table->string('rol_docente', 60)->default('docente_aula'); // texto libre configurable
            $table->timestamps();

            // Un docente no se asigna dos veces al mismo curso en la misma gestión.
            $table->unique(['user_id', 'curso_id', 'gestion_id']);
        });

        // Horarios por curso y gestión. Se guarda desde cuándo rige cada horario
        // para que un cambio futuro no cambie el significado de registros pasados.
        Schema::create('horarios_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 1=lunes .. 7=domingo
            $table->string('turno', 20);               // manana, tarde
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('vigente_desde')->nullable(); // desde cuándo rige este horario
            $table->timestamps();

            // Evita cargar dos veces el mismo bloque horario para un curso.
            $table->unique(['curso_id', 'dia_semana', 'turno', 'hora_inicio']);
        });

        // Calendario que se aplica a la asistencia: jornadas sin clases,
        // feriados y otras excepciones de la gestión (o de un curso específico).
        Schema::create('calendario_excepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            // Si curso_id es null, la excepción vale para todo el colegio.
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo', 30)->default('sin_clases'); // sin_clases, feriado, actividad_interna
            $table->string('motivo', 255)->nullable();
            $table->timestamps();

            // No se repite la misma excepción para el mismo día y alcance.
            $table->unique(['gestion_id', 'curso_id', 'fecha', 'tipo']);
            // Índice para consultar rápido si una fecha tiene excepción.
            $table->index('fecha');
        });
    }

    /**
     * Revierte la migración eliminando las tablas en orden inverso.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendario_excepciones');
        Schema::dropIfExists('horarios_curso');
        Schema::dropIfExists('docente_curso');
    }
};
