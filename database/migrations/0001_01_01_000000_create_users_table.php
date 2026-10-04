<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración base de usuarios (viene con Laravel).
 *
 * Crea tres tablas necesarias para el acceso al sistema:
 * - users: las cuentas de todas las personas que usan el sistema (personal del
 *   colegio y responsables familiares). Los roles se asignan aparte con
 *   spatie/laravel-permission y los datos extra del perfil (documento,
 *   teléfono, etc.) se agregan en una migración posterior.
 * - password_reset_tokens: tokens temporales para recuperar la contraseña.
 * - sessions: sesiones abiertas, porque guardamos las sesiones en la base de
 *   datos en lugar de en archivos.
 */
return new class extends Migration
{
    /**
     * Crea las tablas de usuarios, recuperación de contraseña y sesiones.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // El correo es único porque se usa como nombre de usuario para iniciar sesión.
            $table->string('email')->unique();
            // Fecha en que el usuario verificó su correo (null si aún no lo hizo).
            $table->timestamp('email_verified_at')->nullable();
            // Contraseña cifrada con hash; nunca se guarda en texto plano.
            $table->string('password');
            // Token para la opción "Recordarme" del inicio de sesión.
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            // Un solo token vigente por correo, por eso el correo es la clave primaria.
            $table->string('email')->primary();
            $table->string('token');
            // Sirve para saber cuándo vence el token.
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            // Usuario dueño de la sesión (null si todavía no inició sesión).
            $table->foreignId('user_id')->nullable()->index();
            // Datos del equipo desde el que se conecta (45 caracteres alcanzan para IPv6).
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            // Contenido serializado de la sesión.
            $table->longText('payload');
            // Momento de la última actividad, indexado para limpiar sesiones vencidas.
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Revierte la migración eliminando las tres tablas.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
