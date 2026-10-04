<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla de auditoría.
 *
 * Aquí registramos las acciones sensibles del sistema (validar un pago,
 * cambiar el rol de un usuario, anular algo, etc.) para saber después quién
 * hizo qué y cuándo. Es una herramienta de control y transparencia.
 */
return new class extends Migration
{
    /**
     * Crea la tabla auditoria.
     */
    public function up(): void
    {
        // Auditoría acotada de acciones sensibles: usuario, fecha, acción y
        // registro afectado. No se guardan contraseñas ni contenido confidencial.
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            // Usuario que hizo la acción. Si después se borra la cuenta, el registro se conserva.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 80);                  // p. ej. pagos.validar, usuarios.asignar_rol
            // Registro afectado, guardado como relación polimórfica (tipo de modelo + id).
            $table->string('subject_type', 120)->nullable(); // modelo afectado
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('datos')->nullable();             // solo datos relevantes que no sean sensibles
            $table->string('ip', 45)->nullable();
            // Solo fecha de creación: un registro de auditoría nunca se modifica.
            $table->timestamp('created_at')->useCurrent();

            // Índices para buscar por registro afectado, por usuario y fecha, o por acción.
            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('accion');
        });
    }

    /**
     * Revierte la migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
