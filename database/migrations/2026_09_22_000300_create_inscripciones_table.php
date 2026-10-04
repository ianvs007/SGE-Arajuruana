<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla de inscripciones.
 *
 * Esta tabla guarda las inscripciones de cada estudiante por gestión, así
 * podemos conservar su historial año tras año: en qué curso estuvo cada año y
 * en qué estado terminó (activo, retirado, trasladado, etc.).
 */
return new class extends Migration
{
    /**
     * Crea la tabla inscripciones.
     */
    public function up(): void
    {
        // Separamos la identidad del alumno (tabla estudiantes) de su inscripción:
        // el alumno conserva siempre sus datos personales y cada inscripción lo
        // relaciona con una gestión y un curso.
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            // restrictOnDelete: no se puede borrar un curso que tenga alumnos inscritos.
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->string('estado', 30)->default('activa'); // activa, retirada, trasladada, cancelada
            $table->date('fecha_inscripcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Un alumno no se inscribe dos veces en la misma gestión.
            $table->unique(['estudiante_id', 'gestion_id']);
            // Índice para listar rápido los alumnos de un curso en una gestión.
            $table->index(['gestion_id', 'curso_id']);
        });
    }

    /**
     * Revierte la migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
