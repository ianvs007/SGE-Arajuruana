<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('documento', 30)->nullable()->unique()->after('id');
            $table->string('telefono', 30)->nullable()->after('email');
            $table->string('direccion')->nullable()->after('telefono');
            $table->boolean('activo')->default(true)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['documento', 'telefono', 'direccion', 'activo']);
        });
    }
};
