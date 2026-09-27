<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separa identidad (estudiantes) de inscripción (§7): el alumno conserva
        // su identidad y cada inscripción lo relaciona con gestión y curso.
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('gestion_id')->constrained('gestiones')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->string('estado', 30)->default('activa'); // activa, retirada, trasladada, cancelada
            $table->date('fecha_inscripcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Un alumno no se inscribe dos veces en la misma gestión.
            $table->unique(['estudiante_id', 'gestion_id']);
            $table->index(['gestion_id', 'curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
