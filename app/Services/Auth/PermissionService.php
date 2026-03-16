<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Auth\Permission;
use Illuminate\Support\Collection;

class PermissionService
{
    /**
     * Standard actions applied to most modules.
     */
    private const STANDARD_ACTIONS = [
        'view_any', 'view', 'create', 'update', 'delete',
        'restore', 'force_delete', 'export', 'import', 'print',
    ];

    /**
     * Full permissions matrix: module => extra actions beyond standard.
     * Modules with only standard actions have an empty array.
     */
    private const MODULES = [
        'companies' => [],
        'users' => [],
        'roles' => [],
        'permissions' => [],
        'personnels' => [],
        'departments' => [],
        'positions' => [],
        'contracts' => [],
        'leaves' => ['approve', 'reject'],
        'attendances' => [],
        'customers' => [],
        'quotes' => ['send', 'accept', 'reject', 'convert', 'duplicate'],
        'sales_orders' => ['confirm', 'cancel', 'generate_invoice', 'generate_delivery'],
        'invoices' => ['send', 'cancel', 'record_payment', 'create_credit_note'],
        'credit_notes' => ['confirm', 'apply'],
        'delivery_notes' => ['ship', 'deliver'],
        'suppliers' => [],
        'purchase_requests' => ['submit', 'approve', 'reject', 'convert'],
        'purchase_orders' => ['send', 'confirm', 'cancel', 'generate_reception', 'generate_invoice'],
        'reception_notes' => ['confirm', 'cancel'],
        'purchase_invoices'  => ['record_payment'],
        'products'           => [],
        'product_categories' => [],
        'warehouses'         => [],
        'stock_movements'    => ['transfer'],
        'stock_inventories'  => ['start', 'validate'],
        'currencies'         => [],
        'taxes'              => [],
        'payment_terms'      => [],
        'payment_methods'    => [],
        'chart_of_accounts'  => [],
        'bank_accounts'      => [],
        'journal_entries'    => ['post', 'cancel'],
        'payments'           => ['confirm', 'cancel'],
        'cautions'           => ['activate', 'partial_return', 'full_return', 'extend', 'forfeit', 'cancel'],
        'caution_types'      => [],
        'events'             => ['confirm', 'cancel', 'complete'],
        'event_categories'   => [],
        'event_participants' => ['invite', 'bulk_invite'],
        'contacts'           => ['merge'],
        'leads'              => ['qualify', 'convert', 'assign'],
        'opportunities'      => ['advance', 'win', 'lose'],
        'activities'         => ['complete'],
        'projects'           => [],
        'tasks'              => ['assign', 'change_status'],
        'timesheets'         => ['submit', 'approve', 'reject'],
        'settings'           => [],
        'sequences'          => [],
        // Restricted modules — only specific actions
        'audit_logs'         => null,   // view_any, view only
        'notifications'      => null,   // view_any, view, delete only
        'attachments'        => null,   // view_any, view, create, delete only
    ];

    /**
     * Restricted module action overrides (replaces standard actions entirely).
     */
    private const RESTRICTED_ACTIONS = [
        'audit_logs'    => ['view_any', 'view'],
        'notifications' => ['view_any', 'view', 'delete'],
        'attachments'   => ['view_any', 'view', 'create', 'delete'],
    ];

    /**
     * Build the full permissions list as an array of ['module', 'action', 'slug', 'name', 'description'].
     */
    public function buildPermissionsMatrix(): array
    {
        $permissions = [];

        foreach (self::MODULES as $module => $extraActions) {
            $actions = isset(self::RESTRICTED_ACTIONS[$module])
                ? self::RESTRICTED_ACTIONS[$module]
                : array_merge(self::STANDARD_ACTIONS, $extraActions ?? []);

            foreach ($actions as $action) {
                $slug = "{$module}.{$action}";
                $permissions[] = [
                    'module'      => $module,
                    'name'        => $this->formatName($module, $action),
                    'slug'        => $slug,
                    'description' => "Allow {$action} on {$module}",
                ];
            }
        }

        return $permissions;
    }

    /**
     * Seed all permissions into the database (upsert — safe to run multiple times).
     */
    public function seedAllPermissions(): void
    {
        $now = now();

        foreach ($this->buildPermissionsMatrix() as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                array_merge($permission, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * Get all permissions for a given module.
     */
    public function getByModule(string $module): Collection
    {
        return Permission::where('module', $module)->orderBy('slug')->get();
    }

    /**
     * Get all permissions grouped by module.
     *
     * @return array<string, Collection>
     */
    public function getAllGroupedByModule(): array
    {
        return Permission::orderBy('module')->orderBy('slug')
            ->get()
            ->groupBy('module')
            ->toArray();
    }

    /**
     * Format a human-readable name from module + action.
     * e.g. "sales_orders" + "generate_invoice" → "Sales Orders: Generate Invoice"
     */
    private function formatName(string $module, string $action): string
    {
        $moduleLabel  = ucwords(str_replace('_', ' ', $module));
        $actionLabel  = ucwords(str_replace('_', ' ', $action));

        return "{$moduleLabel}: {$actionLabel}";
    }
}
