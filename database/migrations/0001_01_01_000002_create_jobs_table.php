<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración base de colas de trabajo (viene con Laravel).
 *
 * Crea las tablas para ejecutar tareas en segundo plano (por ejemplo, el envío
 * de correos) cuando la cola usa la base de datos: los trabajos pendientes, los
 * lotes de trabajos y los trabajos que fallaron.
 */
return new class extends Migration
{
    /**
     * Crea las tablas de trabajos, lotes y trabajos fallidos.
     */
    public function up(): void
    {
        // Trabajos en espera de ser procesados.
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            // Nombre de la cola a la que pertenece el trabajo.
            $table->string('queue')->index();
            // Datos serializados del trabajo a ejecutar.
            $table->longText('payload');
            // Cuántas veces se intentó ejecutar.
            $table->unsignedTinyInteger('attempts');
            // Marcas de tiempo (en formato Unix) de reserva, disponibilidad y creación.
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        // Lotes de trabajos que se procesan como un grupo y se siguen en conjunto.
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            // Contadores para conocer el avance del lote.
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        // Trabajos que fallaron, guardados para revisar el error o reintentarlos.
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            // Identificador único del trabajo fallido.
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            // Detalle del error que provocó la falla.
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Revierte la migración eliminando las tres tablas.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
