<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega datos de perfil a la tabla users.
 *
 * La tabla que trae Laravel solo tiene nombre, correo y contraseña. Para el
 * colegio necesitamos además el documento de identidad, un teléfono de contacto
 * (sobre todo para los responsables familiares), la dirección y un indicador
 * para desactivar cuentas sin tener que borrarlas.
 */
return new class extends Migration
{
    /**
     * Añade las columnas nuevas al perfil del usuario.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Carnet de identidad. Es opcional pero, si se carga, no puede repetirse.
            $table->string('documento', 30)->nullable()->unique()->after('id');
            $table->string('telefono', 30)->nullable()->after('email');
            $table->string('direccion')->nullable()->after('telefono');
            // Permite bloquear el acceso de una cuenta conservando su historial.
            $table->boolean('activo')->default(true)->after('remember_token');
        });
    }

    /**
     * Revierte la migración quitando las columnas agregadas.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['documento', 'telefono', 'direccion', 'activo']);
        });
    }
};
