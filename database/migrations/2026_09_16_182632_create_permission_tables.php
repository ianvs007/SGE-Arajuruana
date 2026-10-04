<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de roles y permisos (paquete spatie/laravel-permission).
 *
 * Esta migración la publica el propio paquete y es la base de todo el control
 * de acceso del sistema. Crea cinco tablas:
 * - permissions: el catálogo de permisos (por ejemplo "asistencia.gestionar").
 * - roles: los roles del colegio (Administración, Director, Docente, etc.).
 * - model_has_permissions: permisos asignados directamente a un usuario.
 * - model_has_roles: qué rol tiene cada usuario.
 * - role_has_permissions: qué permisos tiene cada rol (la matriz rol-permiso).
 *
 * Los nombres de tablas y columnas se leen de config/permission.php, así el
 * paquete se puede adaptar sin modificar esta migración.
 */
return new class extends Migration
{
    /**
     * Crea las tablas de roles y permisos y limpia la caché de permisos.
     */
    public function up(): void
    {
        // Leemos la configuración del paquete: si se usan "equipos", los nombres
        // de las tablas y los nombres de las columnas pivote.
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        // Si la configuración no está cargada detenemos la migración con un
        // mensaje claro, en lugar de crear tablas con nombres incorrectos.
        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        throw_if($teams && empty($columnNames['team_foreign_key'] ?? null), Exception::class, 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        // Catálogo de permisos.
        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            // $table->engine('InnoDB');
            $table->bigIncrements('id'); // id del permiso
            $table->string('name');       // Con MyISAM conviene string('name', 225) (o 166 en InnoDB con formato de fila Redundant/Compact)
            $table->string('guard_name'); // Con MyISAM conviene string('guard_name', 25)
            $table->timestamps();

            // No puede repetirse el mismo permiso dentro del mismo guard.
            $table->unique(['name', 'guard_name']);
        });

        // Catálogo de roles.
        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $columnNames) {
            // $table->engine('InnoDB');
            $table->bigIncrements('id'); // id del rol
            if ($teams || config('permission.testing')) { // permission.testing es un ajuste para las pruebas con SQLite
                // Solo si se usan equipos: cada rol puede pertenecer a un equipo.
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name');       // Con MyISAM conviene string('name', 225) (o 166 en InnoDB con formato de fila Redundant/Compact)
            $table->string('guard_name'); // Con MyISAM conviene string('guard_name', 25)
            $table->timestamps();
            // El nombre del rol es único por guard (y por equipo, si se usan equipos).
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        // Permisos asignados directamente a un modelo (normalmente un usuario),
        // sin pasar por un rol. Usa una relación polimórfica (model_type + id).
        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $table->unsignedBigInteger($pivotPermission);

            // Tipo de modelo (por ejemplo App\Models\User) y su id.
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            // Si se borra el permiso, también se borran sus asignaciones.
            $table->foreign($pivotPermission)
                ->references('id') // id del permiso
                ->on($tableNames['permissions'])
                ->onDelete('cascade');
            // La clave primaria compuesta evita asignar dos veces el mismo permiso.
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                $table->primary([$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            }

        });

        // Roles asignados a cada usuario (también mediante relación polimórfica).
        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            // Si se borra el rol, se quitan también sus asignaciones a usuarios.
            $table->foreign($pivotRole)
                ->references('id') // id del rol
                ->on($tableNames['roles'])
                ->onDelete('cascade');
            // Clave primaria compuesta: un usuario no recibe dos veces el mismo rol.
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                $table->primary([$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            }
        });

        // Matriz rol-permiso: qué permisos incluye cada rol.
        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            // Ambas claves foráneas se borran en cascada para no dejar registros huérfanos.
            $table->foreign($pivotPermission)
                ->references('id') // id del permiso
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id') // id del rol
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        // Borramos la caché de permisos para que el paquete vuelva a leerlos
        // desde las tablas recién creadas.
        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Revierte la migración eliminando las tablas. Primero se borran las tablas
     * intermedias y al final roles y permisos, para respetar las claves foráneas.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
