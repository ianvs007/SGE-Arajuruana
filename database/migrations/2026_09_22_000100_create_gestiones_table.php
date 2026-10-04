<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla de gestiones académicas.
 *
 * En Bolivia el año escolar se llama "gestión". Guardamos cada gestión en una
 * tabla propia para que el sistema funcione año tras año sin tener el año
 * escrito en el código: los cursos, inscripciones y cuotas se relacionan con
 * una gestión, y así se conserva el historial de cada año.
 */
return new class extends Migration
{
    /**
     * Crea la tabla gestiones.
     */
    public function up(): void
    {
        // Gestión académica configurable desde el sistema. No se fija el año en el código.
        Schema::create('gestiones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);            // p. ej. "Gestión 2026"
            $table->unsignedSmallInteger('anio');    // p. ej. 2026
            // Fechas de inicio y fin de clases de la gestión.
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('es_actual')->default(false); // solo una puede estar vigente (lo controla la aplicación)
            $table->boolean('activa')->default(true);     // las gestiones históricas quedan en false
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // No puede haber dos gestiones para el mismo año.
            $table->unique('anio');
        });
    }

    /**
     * Revierte la migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestiones');
    }
};
