<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Exception;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        try {
            // Clear cached roles and permissions
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $permissions = [
                'edit_profile',
                'reset_password',
                'manage_users',
                'add_property',
                'view_dashboard',
            ];

            // Create permissions if not existing
            foreach ($permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission]);
            }

            // Create roles
            $admin = Role::firstOrCreate(['name' => 'admin']);
            $user = Role::firstOrCreate(['name' => 'user']);

            // Assign permissions to roles
            $admin->syncPermissions($permissions);
            $user->syncPermissions(['edit_profile', 'add_property', 'view_dashboard']);
        } catch (Exception $e) {
            $this->command->error('Seeding failed: ' . $e->getMessage());
        }
    }
}
