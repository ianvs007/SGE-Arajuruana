<?php

namespace Database\Seeders;

use App\Support\Permisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza el catálogo de permisos y la matriz rol → permisos (§5) con la
 * base de datos, SIN tocar datos de negocio.
 *
 * Es idempotente y seguro de ejecutar en producción: tras cualquier cambio en
 * `App\Support\Permisos` basta con correr
 * `php artisan db:seed --class=RolePermissionSeeder` para alinear los roles.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permisos::TODOS as $permiso) {
            Permission::findOrCreate($permiso);
        }

        foreach (Permisos::POR_ROL as $nombreRol => $permisos) {
            Role::findOrCreate($nombreRol)->syncPermissions($permisos);
        }
    }
}
