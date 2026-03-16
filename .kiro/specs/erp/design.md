# ERP Multi-Société — Technical Design

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                   Browser (Blade + Tailwind)          │
├─────────────────────────────────────────────────────┤
│              Laravel Routes (web + api)               │
├──────────┬──────────┬──────────┬────────────────────┤
│ Middleware│ Middleware│ Middleware│                    │
│ auth:    │ SetCurrent│ Check    │  Rate Limiter      │
│ sanctum  │ Company   │Permission│                    │
├──────────┴──────────┴──────────┴────────────────────┤
│                  Controllers                          │
│   (thin — only HTTP handling, delegates to services)  │
├─────────────────────────────────────────────────────┤
│            FormRequests (validation layer)             │
├─────────────────────────────────────────────────────┤
│                  Service Layer                        │
│         (all business logic lives here)               │
├─────────────────────────────────────────────────────┤
│            Eloquent Models + Policies                 │
│    (BelongsToCompany trait auto-scopes queries)       │
├─────────────────────────────────────────────────────┤
│               PostgreSQL Database                     │
│         (single DB, company_id on all tables)         │
└─────────────────────────────────────────────────────┘
```

## Folder Structure (Standard Laravel MVC + Domain Subfolders)

```
app/
├── Models/
│   ├── Auth/              → User.php, Role.php, Permission.php
│   ├── Company/           → Company.php
│   ├── Personnel/         → Personnel.php, Department.php, Position.php, Contract.php, Leave.php, Attendance.php
│   ├── Sales/             → Customer.php, Quote.php, QuoteLine.php, SalesOrder.php, SalesOrderLine.php, Invoice.php, InvoiceLine.php, CreditNote.php, CreditNoteLine.php, DeliveryNote.php, DeliveryNoteLine.php
│   ├── Purchasing/        → Supplier.php, PurchaseRequest.php, PurchaseRequestLine.php, PurchaseOrder.php, PurchaseOrderLine.php, ReceptionNote.php, ReceptionNoteLine.php, PurchaseInvoice.php, PurchaseInvoiceLine.php
│   ├── Inventory/         → Product.php, ProductCategory.php, Warehouse.php, StockMovement.php, StockLevel.php, StockInventory.php, StockInventoryLine.php
│   ├── Finance/           → Currency.php, Tax.php, PaymentTerm.php, PaymentMethod.php, ChartOfAccount.php, BankAccount.php, JournalEntry.php, JournalEntryLine.php, Payment.php
│   ├── Caution/           → CautionType.php, Caution.php, CautionHistory.php
│   ├── Event/             → EventCategory.php, Event.php, EventParticipant.php
│   ├── CRM/               → Contact.php, Lead.php, Opportunity.php, Activity.php
│   ├── Project/           → Project.php, Task.php, Timesheet.php
│   └── Settings/          → Setting.php, Sequence.php, AuditLog.php, Notification.php, Attachment.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/          → AuthController.php, RoleController.php, PermissionController.php
│   │   ├── Company/       → CompanyController.php
│   │   ├── Personnel/     → PersonnelController.php, DepartmentController.php, PositionController.php, ContractController.php, LeaveController.php, AttendanceController.php
│   │   ├── Sales/         → CustomerController.php, QuoteController.php, SalesOrderController.php, InvoiceController.php, CreditNoteController.php, DeliveryNoteController.php
│   │   ├── Purchasing/    → SupplierController.php, PurchaseRequestController.php, PurchaseOrderController.php, ReceptionNoteController.php, PurchaseInvoiceController.php
│   │   ├── Inventory/     → ProductController.php, ProductCategoryController.php, WarehouseController.php, StockMovementController.php, StockInventoryController.php
│   │   ├── Finance/       → CurrencyController.php, TaxController.php, ChartOfAccountController.php, BankAccountController.php, JournalEntryController.php, PaymentController.php
│   │   ├── Caution/       → CautionTypeController.php, CautionController.php
│   │   ├── Event/         → EventCategoryController.php, EventController.php, EventParticipantController.php
│   │   ├── CRM/           → ContactController.php, LeadController.php, OpportunityController.php, ActivityController.php
│   │   ├── Project/       → ProjectController.php, TaskController.php, TimesheetController.php
│   │   └── Settings/      → SettingController.php, SequenceController.php, NotificationController.php
│   │
│   ├── Requests/
│   │   ├── Auth/          → LoginRequest.php, RegisterRequest.php, ChangePasswordRequest.php, StoreRoleRequest.php, UpdateRoleRequest.php
│   │   ├── Company/       → StoreCompanyRequest.php, UpdateCompanyRequest.php
│   │   ├── Sales/         → StoreQuoteRequest.php, UpdateQuoteRequest.php, StoreInvoiceRequest.php, ...
│   │   ├── Purchasing/    → StorePurchaseOrderRequest.php, ...
│   │   └── ... (one subfolder per domain)
│   │
│   ├── Resources/
│   │   ├── Auth/          → UserResource.php, RoleResource.php, PermissionResource.php
│   │   ├── Company/       → CompanyResource.php
│   │   ├── Sales/         → CustomerResource.php, QuoteResource.php, InvoiceResource.php, ...
│   │   └── ... (one subfolder per domain)
│   │
│   └── Middleware/
│       ├── SetCurrentCompany.php
│       ├── CheckPermission.php
│       └── AuditLog.php
│
├── Services/
│   ├── Auth/              → AuthService.php, RoleService.php, PermissionService.php
│   ├── Company/           → CompanyService.php
│   ├── Personnel/         → PersonnelService.php, DepartmentService.php, ContractService.php, LeaveService.php, AttendanceService.php
│   ├── Sales/             → CustomerService.php, QuoteService.php, SalesOrderService.php, InvoiceService.php, CreditNoteService.php, DeliveryNoteService.php
│   ├── Purchasing/        → SupplierService.php, PurchaseRequestService.php, PurchaseOrderService.php, ReceptionNoteService.php, PurchaseInvoiceService.php
│   ├── Inventory/         → ProductService.php, ProductCategoryService.php, WarehouseService.php, StockMovementService.php, StockInventoryService.php
│   ├── Finance/           → CurrencyService.php, TaxService.php, ChartOfAccountService.php, BankAccountService.php, JournalEntryService.php, PaymentService.php
│   ├── Caution/           → CautionTypeService.php, CautionService.php
│   ├── Event/             → EventService.php, EventCategoryService.php, EventParticipantService.php
│   ├── CRM/               → ContactService.php, LeadService.php, OpportunityService.php, ActivityService.php
│   ├── Project/           → ProjectService.php, TaskService.php, TimesheetService.php
│   └── Settings/          → SettingService.php, SequenceService.php, AuditLogService.php, NotificationService.php, AttachmentService.php
│
├── Policies/
│   ├── Auth/              → RolePolicy.php
│   ├── Company/           → CompanyPolicy.php
│   ├── Sales/             → QuotePolicy.php, InvoicePolicy.php, ...
│   └── ... (one subfolder per domain)
│
└── Traits/
    ├── BelongsToCompany.php
    ├── HasAuditTrail.php
    ├── GeneratesReference.php
    └── HasStatus.php
```

**Namespace examples:**
- `App\Models\Sales\Invoice`
- `App\Http\Controllers\Sales\InvoiceController`
- `App\Services\Sales\InvoiceService`
- `App\Http\Requests\Sales\StoreInvoiceRequest`
- `App\Http\Resources\Sales\InvoiceResource`
- `App\Policies\Sales\InvoicePolicy`

## Multi-Tenancy Design

### BelongsToCompany Trait

Applied to ALL company-scoped models. Automatically:
1. Sets `company_id` on creation from `auth()->user()->current_company_id`
2. Adds global scope filtering by `company_id`
3. Provides `company()` BelongsTo relationship

```php
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (!$model->company_id) {
                $model->company_id = auth()->user()?->current_company_id;
            }
        });
        static::addGlobalScope('company', function ($query) {
            if (auth()->check()) {
                $query->where($query->getModel()->getTable() . '.company_id', auth()->user()->current_company_id);
            }
        });
    }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
```

### SetCurrentCompany Middleware

```php
class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-Id') ?? $user->current_company_id;

        if (!$companyId) abort(403, 'No company selected.');
        if (!$user->companies()->where('companies.id', $companyId)->exists()) {
            abort(403, 'Access denied to this company.');
        }

        if ($user->current_company_id !== (int)$companyId) {
            $user->update(['current_company_id' => $companyId]);
        }

        return $next($request);
    }
}
```

## Authentication Design (Sanctum)

### Token Flow
1. `POST /auth/login` → validates credentials → creates Sanctum token → returns `{ user, token, companies }`
2. All subsequent requests include `Authorization: Bearer {token}` + `X-Company-Id: {id}`
3. `SetCurrentCompany` middleware validates company membership
4. `CheckPermission` middleware validates action permission

### Permission Resolution Order (Simple 3-table RBAC)

**Tables**: `users` ←→ `roles` (M2M via `role_user`) ←→ `permissions` (M2M via `permission_role`)

Resolution flow:
1. Get user's roles for current company: `role_user WHERE user_id = ? AND company_id = ?`
2. Get all permissions from those roles: `permission_role WHERE role_id IN (?)`
3. Check if target permission slug exists in collected permissions → ALLOW/DENY
4. Default: DENY

```php
// User model method
public function hasPermission(string $permissionSlug): bool
{
    return $this->roles()
        ->wherePivot('company_id', $this->current_company_id)
        ->whereHas('permissions', fn($q) => $q->where('slug', $permissionSlug))
        ->exists();
}

// CheckPermission middleware
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (!$user) abort(401);
        if (!$user->current_company_id) abort(403, 'No company selected');

        if (!$user->hasPermission($permission)) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
```

**Blade usage:**
```blade
@if(auth()->user()->hasPermission('invoices.create'))
    <a href="{{ route('invoices.create') }}">New Invoice</a>
@endif
```

**Controller usage:**
```php
Route::middleware(['auth:sanctum', 'company', 'permission:invoices.create'])
    ->post('/invoices', [InvoiceController::class, 'store']);
```

## Database Schema Overview

### Entity Relationship Summary

```
User ←→ Company (M2M via company_user)
User ←→ Role (M2M via role_user with company_id)
Role ←→ Permission (M2M via permission_role)
User → Personnel (1:1 optional via user_id)
Personnel → Department (M:1)
Personnel → Position (M:1)
Personnel → Contract (1:M)
Personnel → Leave (1:M)
Personnel → Attendance (1:M)

Customer → Quote (1:M) → QuoteLine (1:M)
Quote → SalesOrder (1:1 via conversion)
Customer → SalesOrder (1:M) → SalesOrderLine (1:M)
SalesOrder → Invoice (1:M) → InvoiceLine (1:M)
SalesOrder → DeliveryNote (1:M) → DeliveryNoteLine (1:M)
Invoice → CreditNote (1:M) → CreditNoteLine (1:M)
Invoice → Payment (1:M)

Supplier → PurchaseRequest (indirect)
Supplier → PurchaseOrder (1:M) → PurchaseOrderLine (1:M)
PurchaseOrder → ReceptionNote (1:M) → ReceptionNoteLine (1:M)
PurchaseOrder → PurchaseInvoice (1:M) → PurchaseInvoiceLine (1:M)

Product → ProductCategory (M:1)
Product → StockMovement (1:M)
Product + Warehouse → StockLevel (composite)
StockInventory → StockInventoryLine (1:M)

ChartOfAccount → JournalEntryLine (1:M)
JournalEntry → JournalEntryLine (1:M)
Payment → JournalEntry (1:1)

Caution → CautionType (M:1)
Caution → CautionHistory (1:M)

Event → EventCategory (M:1)
Event → EventParticipant (1:M)

Contact → Lead (1:M)
Lead → Opportunity (1:1 via conversion)
Activity → polymorphic (Contact, Lead, Opportunity, Customer)

Project → Task (1:M, hierarchical)
Project → Timesheet (1:M)
```

### Key Tables Count: ~55 tables

### Migration Order (dependencies)
1. `companies`
2. `users` (modify existing — add matricule, current_company_id — NO is_superadmin)
3. `company_user` (user_id, company_id, is_default, joined_at — NO role_id)
4. `roles` (company_id nullable for global roles)
5. `permissions` (module, name, slug)
6. `permission_role` (pivot: role_id, permission_id)
7. `role_user` (pivot: role_id, user_id, company_id — so user can have different roles per company)
8. `password_histories`, `login_attempts`
9. `departments`
10. `positions`
11. `personnels`
12. `contracts`, `leaves`, `attendances`
13. `currencies`, `taxes`, `payment_terms`, `payment_methods`
14. `product_categories`, `products`
15. `warehouses`, `stock_levels`, `stock_movements`
16. `stock_inventories`, `stock_inventory_lines`
17. `customers`, `suppliers`
18. `quotes`, `quote_lines`
19. `sales_orders`, `sales_order_lines`
20. `invoices`, `invoice_lines`
21. `credit_notes`, `credit_note_lines`
22. `delivery_notes`, `delivery_note_lines`
23. `purchase_requests`, `purchase_request_lines`
24. `purchase_orders`, `purchase_order_lines`
25. `reception_notes`, `reception_note_lines`
26. `purchase_invoices`, `purchase_invoice_lines`
27. `chart_of_accounts`, `bank_accounts`
28. `journal_entries`, `journal_entry_lines`
29. `payments`
30. `caution_types`, `cautions`, `caution_histories`
31. `event_categories`, `events`, `event_participants`
32. `contacts`, `leads`, `opportunities`, `activities`
33. `projects`, `tasks`, `timesheets`
34. `settings`, `sequences`
35. `audit_logs`, `notifications`, `document_attachments`

## Service Layer Pattern

Every service follows this pattern:

```php
class QuoteService
{
    public function __construct(
        private readonly SequenceService $sequenceService,
    ) {}

    public function create(array $data): Quote
    {
        return DB::transaction(function () use ($data) {
            $data['reference'] = $this->sequenceService->getNextNumber(Quote::class);
            $data['created_by'] = auth()->id();

            $quote = Quote::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $lineData) {
                    $line = $this->calculateLineAmounts($lineData);
                    $quote->lines()->create($line);
                }
            }

            $this->calculateTotals($quote);
            return $quote->fresh(['lines', 'customer']);
        });
    }

    // ... other methods
}
```

## Calculation Formulas

### Line Amount Calculation
```
line_subtotal_ht = quantity × unit_price_ht
if discount_type == 'percentage':
    line_discount = line_subtotal_ht × (discount_value / 100)
else:
    line_discount = discount_value
line_subtotal_ht_after_discount = line_subtotal_ht - line_discount
tax_amount = line_subtotal_ht_after_discount × (tax_rate / 100)
line_total_ttc = line_subtotal_ht_after_discount + tax_amount
```

### Document Total Calculation
```
subtotal_ht = SUM(lines.subtotal_ht)
total_discount = SUM(lines discounts) + document-level discount
total_tax = SUM(lines.tax_amount)
total_ttc = subtotal_ht - total_discount + total_tax

For invoices:
amount_due = total_ttc - amount_paid
```

## Security Architecture

### Middleware Stack (for protected routes)
```
auth:sanctum → SetCurrentCompany → CheckPermission:{slug} → AuditLog
```

### Rate Limiting Configuration
```php
// AppServiceProvider
RateLimiter::for('login', fn(Request $r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('api', fn(Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
RateLimiter::for('uploads', fn(Request $r) => Limit::perMinute(10)->by($r->user()->id));
RateLimiter::for('exports', fn(Request $r) => Limit::perMinute(5)->by($r->user()->id));
```

## Frontend Component Architecture

### Blade Component Library
All reusable components in `resources/views/components/`:

| Component | Purpose | Props |
|---|---|---|
| `x-button` | Action buttons | type, variant (primary/secondary/danger), size, href, disabled |
| `x-card` | Content cards | title, footer |
| `x-data-table` | Sortable/filterable table | columns, data, searchable, sortable |
| `x-form-input` | Text input with label+error | name, label, type, value, required, error |
| `x-form-select` | Select dropdown | name, label, options, selected, required |
| `x-modal` | Modal dialog | id, title, maxWidth |
| `x-alert` | Flash messages | type (success/error/warning/info), message |
| `x-badge` | Status badges | color, text |
| `x-status-badge` | Document status | status (maps to color automatically) |
| `x-stats-card` | Dashboard stat card | title, value, icon, trend, color |
| `x-breadcrumb` | Navigation breadcrumb | items [{label, url}] |
| `x-dropdown` | Dropdown menu | align, width |
| `x-pagination` | Paginator | paginator instance |

### Layout Structure
```html
<!-- app.blade.php -->
<div class="flex h-screen bg-gray-100">
    <!-- Sidebar (fixed, collapsible) -->
    <aside class="w-64 bg-white shadow-lg">
        @include('layouts.partials.sidebar')
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top bar -->
        <header class="bg-white shadow">
            @include('layouts.partials.topbar')
        </header>

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>
</div>
```
