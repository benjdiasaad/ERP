<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Standard actions available for every module.
     */
    private array $standardActions = [
        'view_any',
        'view',
        'create',
        'update',
        'delete',
        'restore',
        'force_delete',
        'export',
        'import',
        'print',
    ];

    /**
     * Extra actions per module (in addition to standard actions).
     */
    private array $extraActions = [
        'quotes'            => ['send', 'accept', 'reject', 'convert', 'duplicate'],
        'sales_orders'      => ['confirm', 'cancel', 'generate_invoice', 'generate_delivery_note'],
        'invoices'          => ['send', 'cancel', 'record_payment', 'create_credit_note'],
        'credit_notes'      => ['confirm', 'apply'],
        'delivery_notes'    => ['ship', 'deliver'],
        'purchase_requests' => ['submit', 'approve', 'reject', 'convert_to_order'],
        'purchase_orders'   => ['send', 'confirm', 'cancel', 'generate_reception', 'generate_invoice'],
        'reception_notes'   => ['confirm', 'cancel'],
        'purchase_invoices' => ['record_payment'],
        'stock_movements'   => ['transfer'],
        'stock_inventories' => ['start', 'validate'],
        'journal_entries'   => ['post', 'cancel'],
        'payments'          => ['confirm', 'cancel'],
        'cautions'          => ['activate', 'partial_return', 'full_return', 'extend', 'forfeit', 'cancel'],
        'events'            => ['confirm', 'cancel', 'complete', 'postpone'],
        'leads'             => ['qualify', 'convert', 'assign'],
        'opportunities'     => ['advance', 'win', 'lose'],
        'activities'        => ['complete'],
        'projects'          => ['update_progress'],
        'tasks'             => ['assign', 'change_status'],
        'timesheets'        => ['submit', 'approve', 'reject'],
        'roles'             => ['assign_permissions'],
        'users'             => ['activate', 'deactivate', 'assign_role'],
    ];

    /**
     * All modules grouped by domain.
     */
    private array $modules = [
        // Auth
        'users',
        'roles',
        'permissions',
        // Company
        'companies',
        // Personnel
        'personnels',
        'departments',
        'positions',
        'contracts',
        'leaves',
        'attendances',
        // Sales
        'customers',
        'quotes',
        'sales_orders',
        'invoices',
        'credit_notes',
        'delivery_notes',
        // Purchasing
        'suppliers',
        'purchase_requests',
        'purchase_orders',
        'reception_notes',
        'purchase_invoices',
        // Inventory
        'products',
        'product_categories',
        'warehouses',
        'stock_movements',
        'stock_inventories',
        // Finance
        'currencies',
        'taxes',
        'payment_terms',
        'payment_methods',
        'chart_of_accounts',
        'bank_accounts',
        'journal_entries',
        'payments',
        // Caution
        'caution_types',
        'cautions',
        // Event
        'event_categories',
        'events',
        // CRM
        'contacts',
        'leads',
        'opportunities',
        'activities',
        // Project
        'projects',
        'tasks',
        'timesheets',
        // Settings
        'settings',
        'sequences',
        'audit_logs',
        'notifications',
        'attachments',
    ];

    public function run(): void
    {
        $count = 0;

        foreach ($this->modules as $module) {
            $actions = array_merge(
                $this->standardActions,
                $this->extraActions[$module] ?? []
            );

            foreach ($actions as $action) {
                $slug = "{$module}.{$action}";
                $name = $this->humanReadableName($module, $action);

                Permission::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'module' => $module,
                        'name' => $name,
                        'description' => '',
                    ]
                );

                $count++;
            }
        }

        $this->command->info("PermissionSeeder: {$count} permissions seeded.");
    }

    /**
     * Convert module + action into a human-readable name.
     * e.g. ('invoices', 'view_any') → "View Any Invoices"
     */
    private function humanReadableName(string $module, string $action): string
    {
        $actionLabel = ucwords(str_replace('_', ' ', $action));
        $moduleLabel = ucwords(str_replace('_', ' ', $module));

        return "{$actionLabel} {$moduleLabel}";
    }
}
