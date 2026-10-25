<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Modules accessible to the User role.
     */
    private array $userModules = [
        'customers', 'quotes', 'sales_orders', 'invoices', 'delivery_notes',
        'suppliers', 'purchase_requests', 'purchase_orders', 'reception_notes', 'purchase_invoices',
        'products', 'warehouses', 'stock_movements',
        'cautions', 'events',
        'contacts', 'leads', 'opportunities', 'activities',
        'projects', 'tasks', 'timesheets',
    ];

    /**
     * Sensitive modules where Manager has restricted access.
     */
    private array $managerRestrictedModules = [
        'roles', 'permissions', 'audit_logs', 'sequences',
    ];

    public function run(): void
    {
        $this->seedSuperAdmin();
        $this->seedAdmin();
        $this->seedManager();
        $this->seedUser();
        $this->seedViewer();
    }

    // ─── Super Admin ──────────────────────────────────────────────────────────

    private function seedSuperAdmin(): void
    {
        $role = Role::updateOrCreate(
            ['slug' => 'super_admin'],
            [
                'name'        => 'Super Admin',
                'description' => 'Full access to all modules and actions.',
                'is_system'   => true,
                'company_id'  => null,
            ]
        );

        $permissionIds = Permission::pluck('id');
        $role->permissions()->sync($permissionIds);

        $this->command->info("RoleSeeder: Super Admin seeded with {$permissionIds->count()} permissions.");
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    private function seedAdmin(): void
    {
        $role = Role::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name'        => 'Admin',
                'description' => 'Full access except force_delete and restore on any module.',
                'is_system'   => true,
                'company_id'  => null,
            ]
        );

        $permissionIds = Permission::where(function ($query): void {
            $query->where('name', 'not like', 'Force Delete %')
                  ->where('name', 'not like', 'Restore %');
        })->pluck('id');

        $role->permissions()->sync($permissionIds);

        $this->command->info("RoleSeeder: Admin seeded with {$permissionIds->count()} permissions.");
    }

    // ─── Manager ─────────────────────────────────────────────────────────────

    private function seedManager(): void
    {
        $role = Role::updateOrCreate(
            ['slug' => 'manager'],
            [
                'name'        => 'Manager',
                'description' => 'Operational access: view, create, update, export, print + module actions. No delete/force_delete/restore/import on sensitive modules.',
                'is_system'   => true,
                'company_id'  => null,
            ]
        );

        $slugs = [];

        foreach (array_keys(PermissionSeeder::slugsByModule()) as $module) {
            $isRestricted = in_array($module, $this->managerRestrictedModules, true);

            // Standard actions allowed for manager
            $allowedStandard = ['view_any', 'view', 'create', 'update', 'export', 'print'];

            // On non-restricted modules, also allow delete and import
            if (!$isRestricted) {
                $allowedStandard[] = 'delete';
                $allowedStandard[] = 'import';
            }

            foreach ($allowedStandard as $action) {
                $slugs[] = "{$module}.{$action}";
            }

            // Extra/module-specific actions (all allowed for manager)
            foreach (PermissionSeeder::extraActionsFor($module) as $action) {
                $slugs[] = "{$module}.{$action}";
            }
        }

        $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $this->command->info("RoleSeeder: Manager seeded with {$permissionIds->count()} permissions.");
    }

    // ─── User ─────────────────────────────────────────────────────────────────

    private function seedUser(): void
    {
        $role = Role::updateOrCreate(
            ['slug' => 'user'],
            [
                'name'        => 'User',
                'description' => 'Operational access to day-to-day modules: view, create, update + module-specific actions.',
                'is_system'   => true,
                'company_id'  => null,
            ]
        );

        $slugs = [];

        foreach ($this->userModules as $module) {
            // Standard actions for user role
            foreach (['view_any', 'view', 'create', 'update'] as $action) {
                $slugs[] = "{$module}.{$action}";
            }

            // Extra/module-specific actions
            foreach (PermissionSeeder::extraActionsFor($module) as $action) {
                $slugs[] = "{$module}.{$action}";
            }
        }

        $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $this->command->info("RoleSeeder: User seeded with {$permissionIds->count()} permissions.");
    }

    // ─── Viewer ───────────────────────────────────────────────────────────────

    private function seedViewer(): void
    {
        $role = Role::updateOrCreate(
            ['slug' => 'viewer'],
            [
                'name'        => 'Viewer',
                'description' => 'Read-only access to all modules (view_any and view only).',
                'is_system'   => true,
                'company_id'  => null,
            ]
        );

        $slugs = [];

        foreach (array_keys(PermissionSeeder::slugsByModule()) as $module) {
            $slugs[] = "{$module}.view_any";
            $slugs[] = "{$module}.view";
        }

        $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $this->command->info("RoleSeeder: Viewer seeded with {$permissionIds->count()} permissions.");
    }
}
