<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Auth\Permission;
use App\Services\Auth\PermissionService;
use Database\Seeders\PermissionSeeder;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionService();
    }

    // ─── Seeder completeness ──────────────────────────────────────────────────

    public function test_permission_seeder_seeds_at_least_480_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertGreaterThanOrEqual(480, Permission::count());
    }

    public function test_permission_seeder_represents_all_expected_modules(): void
    {
        $this->seed(PermissionSeeder::class);

        $expectedModules = [
            // Auth
            'users', 'roles', 'permissions',
            // Company
            'companies',
            // Personnel
            'personnels', 'departments', 'positions', 'contracts', 'leaves', 'attendances',
            // Sales
            'customers', 'quotes', 'sales_orders', 'invoices', 'credit_notes', 'delivery_notes',
            // Purchasing
            'suppliers', 'purchase_requests', 'purchase_orders', 'reception_notes', 'purchase_invoices',
            // Inventory
            'products', 'product_categories', 'warehouses', 'stock_movements', 'stock_inventories',
            // Finance
            'currencies', 'taxes', 'payment_terms', 'payment_methods',
            'chart_of_accounts', 'bank_accounts', 'journal_entries', 'payments',
            // Caution
            'caution_types', 'cautions',
            // Event
            'event_categories', 'events',
            // CRM
            'contacts', 'leads', 'opportunities', 'activities',
            // Project
            'projects', 'tasks', 'timesheets',
            // Settings
            'settings', 'sequences', 'audit_logs', 'notifications', 'attachments',
        ];

        $seededModules = Permission::distinct()->pluck('module')->toArray();

        foreach ($expectedModules as $module) {
            $this->assertContains(
                $module,
                $seededModules,
                "Expected module '{$module}' to be present in seeded permissions."
            );
        }
    }

    public function test_permission_seeder_creates_permissions_with_correct_slug_format(): void
    {
        $this->seed(PermissionSeeder::class);

        $invalidSlug = Permission::whereRaw("slug NOT LIKE '%.__%'")->first();

        $this->assertNull(
            $invalidSlug,
            "Found a permission with an invalid slug format (expected 'module.action'): "
            . ($invalidSlug?->slug ?? '')
        );
    }

    // ─── getByModule() ────────────────────────────────────────────────────────

    public function test_get_by_module_returns_only_permissions_for_given_module(): void
    {
        $this->seed(PermissionSeeder::class);

        $permissions = $this->service->getByModule('invoices');

        $this->assertNotEmpty($permissions);

        foreach ($permissions as $permission) {
            $this->assertSame('invoices', $permission->module);
        }
    }

    public function test_get_by_module_returns_collection(): void
    {
        $this->seed(PermissionSeeder::class);

        $result = $this->service->getByModule('invoices');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_get_by_module_returns_empty_collection_for_unknown_module(): void
    {
        $this->seed(PermissionSeeder::class);

        $result = $this->service->getByModule('nonexistent_module');

        $this->assertTrue($result->isEmpty());
    }

    public function test_get_by_module_does_not_return_permissions_from_other_modules(): void
    {
        $this->seed(PermissionSeeder::class);

        $permissions = $this->service->getByModule('invoices');

        $otherModules = $permissions->filter(fn ($p) => $p->module !== 'invoices');

        $this->assertTrue(
            $otherModules->isEmpty(),
            'getByModule returned permissions belonging to other modules.'
        );
    }

    public function test_get_by_module_includes_standard_actions(): void
    {
        $this->seed(PermissionSeeder::class);

        $slugs = $this->service->getByModule('invoices')->pluck('slug')->toArray();

        $standardActions = ['view_any', 'view', 'create', 'update', 'delete'];

        foreach ($standardActions as $action) {
            $this->assertContains(
                "invoices.{$action}",
                $slugs,
                "Expected standard action 'invoices.{$action}' to be present."
            );
        }
    }

    // ─── getAllGroupedByModule() ───────────────────────────────────────────────

    public function test_get_all_grouped_by_module_returns_array(): void
    {
        $this->seed(PermissionSeeder::class);

        $result = $this->service->getAllGroupedByModule();

        $this->assertIsArray($result);
    }

    public function test_get_all_grouped_by_module_keys_are_module_names(): void
    {
        $this->seed(PermissionSeeder::class);

        $result = $this->service->getAllGroupedByModule();

        $seededModules = Permission::distinct()->pluck('module')->toArray();

        foreach (array_keys($result) as $key) {
            $this->assertContains(
                $key,
                $seededModules,
                "Grouped result key '{$key}' is not a known module."
            );
        }
    }

    public function test_get_all_grouped_by_module_each_group_contains_only_its_module_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $result = $this->service->getAllGroupedByModule();

        foreach ($result as $module => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertSame(
                    $module,
                    $permission['module'],
                    "Permission in group '{$module}' has module '{$permission['module']}'."
                );
            }
        }
    }

    public function test_get_all_grouped_by_module_covers_all_seeded_modules(): void
    {
        $this->seed(PermissionSeeder::class);

        $result = $this->service->getAllGroupedByModule();

        $seededModules = Permission::distinct()->pluck('module')->sort()->values()->toArray();
        $groupedKeys   = array_keys($result);
        sort($groupedKeys);

        $this->assertSame($seededModules, $groupedKeys);
    }

    public function test_get_all_grouped_by_module_returns_non_empty_groups(): void
    {
        $this->seed(PermissionSeeder::class);

        $result = $this->service->getAllGroupedByModule();

        $this->assertNotEmpty($result);

        foreach ($result as $module => $permissions) {
            $this->assertNotEmpty(
                $permissions,
                "Module '{$module}' has an empty permissions group."
            );
        }
    }
}
