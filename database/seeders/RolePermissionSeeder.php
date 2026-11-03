<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles and their abilities are written from the catalog rather than typed out here, so the list
     * the code asks about and the list the database holds cannot drift apart.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::ABILITIES as $ability) {
            Permission::findOrCreate($ability, 'web');
        }

        // Cleared again after the inserts: the registrar caches the list it had on the way in, and
        // a role that asks for a permission written a moment later would not find it.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::ROLES as $role => $extra) {
            Role::findOrCreate($role, 'web')->syncPermissions(PermissionCatalog::abilitiesFor($role));
        }
    }
}
