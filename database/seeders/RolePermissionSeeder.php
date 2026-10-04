<?php

namespace Database\Seeders;

use App\Support\Permisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Carga los permisos y los roles del sistema.
 *
 * Sincroniza con la base de datos el catálogo de permisos y la matriz que dice
 * qué permisos tiene cada rol (Administración, Director, Coordinadora,
 * Subdirector, Docente y Responsable Familiar), SIN tocar datos de alumnos,
 * pagos ni ningún otro dato del colegio.
 *
 * La lista de permisos y la matriz no están escritas aquí, sino en
 * `App\Support\Permisos`, para tener una sola fuente que se usa tanto en este
 * seeder como en el resto del sistema.
 *
 * Se puede ejecutar varias veces sin duplicar nada y es seguro usarlo en
 * producción: tras cualquier cambio en `App\Support\Permisos` basta con correr
 * `php artisan db:seed --class=RolePermissionSeeder` para actualizar los roles.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Crea los permisos que falten y asigna a cada rol exactamente sus permisos.
     */
    public function run(): void
    {
        // Limpiamos la caché de permisos de spatie para que los cambios se
        // apliquen de inmediato y no se usen permisos viejos guardados en caché.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // findOrCreate crea el permiso solo si todavía no existe.
        foreach (Permisos::TODOS as $permiso) {
            Permission::findOrCreate($permiso);
        }

        // Para cada rol, lo creamos si no existe y con syncPermissions le
        // dejamos exactamente los permisos de la matriz (agrega los nuevos y
        // quita los que ya no le corresponden).
        foreach (Permisos::POR_ROL as $nombreRol => $permisos) {
            Role::findOrCreate($nombreRol)->syncPermissions($permisos);
        }
    }
}
