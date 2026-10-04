<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración base de caché (viene con Laravel).
 *
 * Crea las tablas que usa Laravel cuando la caché se guarda en la base de
 * datos. En nuestro caso la caché sirve, entre otras cosas, para guardar los
 * permisos de spatie y no consultarlos en cada petición.
 */
return new class extends Migration
{
    /**
     * Crea las tablas de caché y de bloqueos de caché.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            // Clave con la que se guarda cada valor.
            $table->string('key')->primary();
            $table->mediumText('value');
            // Momento de vencimiento, indexado para borrar rápido lo vencido.
            $table->integer('expiration')->index();
        });

        // Bloqueos atómicos: evitan que dos procesos hagan la misma tarea a la vez.
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            // Identifica qué proceso tiene tomado el bloqueo.
            $table->string('owner');
            $table->integer('expiration')->index();
        });
    }

    /**
     * Revierte la migración eliminando ambas tablas.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
