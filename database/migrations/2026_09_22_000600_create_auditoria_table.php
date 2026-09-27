<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Auditoría acotada de acciones sensibles (§6): usuario, fecha, acción y
        // registro afectado. No almacena contraseñas ni contenido confidencial.
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 80);                  // p. ej. pagos.validar, usuarios.asignar_rol
            $table->string('subject_type', 120)->nullable(); // modelo afectado
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('datos')->nullable();             // solo datos no sensibles relevantes
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
