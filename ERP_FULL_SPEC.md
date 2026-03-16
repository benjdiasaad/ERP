# ERP Multi-Société - KIRO Specification

## Overview

A comprehensive multi-company ERP built with Laravel 12, PostgreSQL, Tailwind CSS, and Laravel Sanctum. Each model lives in its own domain folder under `app/Domain/{Module}`. Business logic lives in Service classes, not controllers. Single database with `company_id` tenant isolation. Full TDD with Feature + Unit tests.

---

## Architecture Principles

### Folder Structure

```
app/
├── Domain/
│   ├── Auth/
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Role.php
│   │   │   └── Permission.php
│   │   ├── Services/
│   │   │   ├── AuthService.php
│   │   │   ├── RoleService.php
│   │   │   └── PermissionService.php
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── RoleController.php
│   │   │   └── PermissionController.php
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Company/
│   │   ├── Models/
│   │   │   └── Company.php
│   │   ├── Services/
│   │   │   └── CompanyService.php
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   ├── Middleware/
│   │   │   └── EnsureCompanyAccess.php
│   │   └── Tests/
│   ├── Personnel/
│   │   ├── Models/
│   │   │   ├── Personnel.php
│   │   │   ├── Department.php
│   │   │   ├── Position.php
│   │   │   ├── Contract.php
│   │   │   ├── Leave.php
│   │   │   └── Attendance.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Sales/
│   │   ├── Models/
│   │   │   ├── Customer.php
│   │   │   ├── Quote.php
│   │   │   ├── QuoteLine.php
│   │   │   ├── SalesOrder.php
│   │   │   ├── SalesOrderLine.php
│   │   │   ├── Invoice.php
│   │   │   ├── InvoiceLine.php
│   │   │   ├── CreditNote.php
│   │   │   ├── CreditNoteLine.php
│   │   │   ├── DeliveryNote.php
│   │   │   └── DeliveryNoteLine.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Purchasing/
│   │   ├── Models/
│   │   │   ├── Supplier.php
│   │   │   ├── PurchaseRequest.php
│   │   │   ├── PurchaseRequestLine.php
│   │   │   ├── PurchaseOrder.php
│   │   │   ├── PurchaseOrderLine.php
│   │   │   ├── ReceptionNote.php
│   │   │   ├── ReceptionNoteLine.php
│   │   │   ├── PurchaseInvoice.php
│   │   │   └── PurchaseInvoiceLine.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Inventory/
│   │   ├── Models/
│   │   │   ├── Product.php
│   │   │   ├── ProductCategory.php
│   │   │   ├── Warehouse.php
│   │   │   ├── StockMovement.php
│   │   │   ├── StockInventory.php
│   │   │   └── StockInventoryLine.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Finance/
│   │   ├── Models/
│   │   │   ├── ChartOfAccount.php
│   │   │   ├── JournalEntry.php
│   │   │   ├── JournalEntryLine.php
│   │   │   ├── BankAccount.php
│   │   │   ├── Payment.php
│   │   │   ├── PaymentMethod.php
│   │   │   ├── Tax.php
│   │   │   ├── Currency.php
│   │   │   └── PaymentTerm.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Caution/
│   │   ├── Models/
│   │   │   ├── Caution.php
│   │   │   ├── CautionType.php
│   │   │   └── CautionHistory.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Event/
│   │   ├── Models/
│   │   │   ├── Event.php
│   │   │   ├── EventCategory.php
│   │   │   └── EventParticipant.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── CRM/
│   │   ├── Models/
│   │   │   ├── Contact.php
│   │   │   ├── Lead.php
│   │   │   ├── Opportunity.php
│   │   │   └── Activity.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   ├── Project/
│   │   ├── Models/
│   │   │   ├── Project.php
│   │   │   ├── Task.php
│   │   │   └── Timesheet.php
│   │   ├── Services/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   ├── Policies/
│   │   └── Tests/
│   └── Settings/
│       ├── Models/
│       │   ├── Setting.php
│       │   └── Sequence.php
│       ├── Services/
│       ├── Controllers/
│       ├── Requests/
│       ├── Resources/
│       └── Tests/
├── Http/
│   ├── Middleware/
│   │   ├── SetCurrentCompany.php
│   │   ├── CheckPermission.php
│   │   └── AuditLog.php
│   └── Kernel.php
├── Traits/
│   ├── BelongsToCompany.php
│   ├── HasAuditTrail.php
│   ├── GeneratesReference.php
│   └── HasStatus.php
└── Providers/
    ├── DomainServiceProvider.php
    └── AuthServiceProvider.php
```

### Core Traits

**BelongsToCompany** trait auto-scopes all queries to current company:

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
                $query->where('company_id', auth()->user()->current_company_id);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

---

## TASK 1: Project Setup & Configuration

### Description
Initialize Laravel 12 project with PostgreSQL, Sanctum, Tailwind CSS, and base folder structure.

### Steps
1. Configure `.env` for PostgreSQL
2. Install `laravel/sanctum` via composer
3. Install Tailwind CSS via npm
4. Create `app/Domain/` folder structure for all modules
5. Create `app/Traits/BelongsToCompany.php`
6. Create `app/Traits/HasAuditTrail.php`
7. Create `app/Traits/GeneratesReference.php`
8. Create `app/Traits/HasStatus.php`
9. Create `app/Providers/DomainServiceProvider.php` to auto-register domain service providers
10. Configure `config/auth.php` for Sanctum guards
11. Create base test helpers and `TestCase` with company tenant support

### Files to Create/Modify
- `.env` — PostgreSQL config
- `composer.json` — add sanctum
- `package.json` — add tailwindcss
- `tailwind.config.js`
- `app/Traits/BelongsToCompany.php`
- `app/Traits/HasAuditTrail.php`
- `app/Traits/GeneratesReference.php`
- `app/Traits/HasStatus.php`
- `app/Providers/DomainServiceProvider.php`
- `config/sanctum.php`
- `tests/TestCase.php`

### Acceptance Criteria
- `php artisan migrate` runs without error on PostgreSQL
- Tailwind compiles with `npm run build`
- Domain folder structure exists
- All traits are loadable
- Base test suite passes

---

## TASK 2: Company (Société) Module

### Description
Multi-company foundation. Users belong to many companies. Each company has isolated data.

### Migration: `companies`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| name | string(255) | not null |
| legal_name | string(255) | nullable |
| tax_id | string(100) | nullable |
| registration_number | string(100) | nullable |
| email | string(255) | nullable |
| phone | string(50) | nullable |
| address | text | nullable |
| city | string(100) | nullable |
| state | string(100) | nullable |
| zip_code | string(20) | nullable |
| country | string(100) | nullable |
| logo_path | string(500) | nullable |
| currency_code | string(3) | default 'MAD' |
| fiscal_year_start | date | nullable |
| is_active | boolean | default true |
| settings | jsonb | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `company_user` (pivot — which companies a user belongs to)

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| user_id | bigint | FK -> users.id |
| is_default | boolean | default false |
| joined_at | timestamp | |
| created_at | timestamp | |
| updated_at | timestamp | |

**NO role_id** — roles are assigned separately via `role_user` pivot table (see TASK 4).

**Unique constraint**: (company_id, user_id)

### Model: `Company`
- Uses SoftDeletes
- Relationships: `users()` BelongsToMany through pivot, `settings()` HasMany
- Scopes: `active()`

### Service: `CompanyService`
- `create(array $data): Company`
- `update(Company $company, array $data): Company`
- `delete(Company $company): bool`
- `addUser(Company $company, User $user, ?Role $role): void`
- `removeUser(Company $company, User $user): void`
- `switchCompany(User $user, Company $company): void`

### Controller: `CompanyController`
- CRUD + `addUser`, `removeUser`, `switchCompany`
- All actions go through `CompanyService`

### FormRequests
- `StoreCompanyRequest` — name required, unique
- `UpdateCompanyRequest` — name required, unique ignoring self

### Policy: `CompanyPolicy`
- viewAny, view, create, update, delete, addUser, removeUser

### Routes (prefix: `/companies`)
- `GET /` — index
- `POST /` — store
- `GET /{company}` — show
- `PUT /{company}` — update
- `DELETE /{company}` — destroy
- `POST /{company}/users` — addUser
- `DELETE /{company}/users/{user}` — removeUser
- `POST /{company}/switch` — switchCompany

### Tests
- Feature: CRUD, add/remove user, switch company, tenant isolation
- Unit: CompanyService methods

---

## TASK 3: Users & Authentication Module

### Description
User model with Sanctum tokens, linked to companies and personnel via matricule. Multi-company login flow.

### Migration: modify `users`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| matricule | string(50) | unique, not null, auto-generated |
| first_name | string(100) | not null |
| last_name | string(100) | not null |
| email | string(255) | unique, not null |
| email_verified_at | timestamp | nullable |
| password | string(255) | not null |
| phone | string(50) | nullable |
| avatar_path | string(500) | nullable |
| current_company_id | bigint | FK -> companies.id, nullable |
| is_active | boolean | default true |
| last_login_at | timestamp | nullable |
| last_login_ip | string(45) | nullable |
| password_changed_at | timestamp | nullable |
| remember_token | string(100) | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

**Matricule format**: `EMP-{YYYY}-{00001}` auto-incremented

### Model: `User`
- Uses HasApiTokens (Sanctum), SoftDeletes
- Hidden: password, remember_token
- Relationships:
  - `companies()` BelongsToMany (via company_user)
  - `currentCompany()` BelongsTo
  - `personnel()` HasOne
  - `roles()` BelongsToMany (via role_user, with company_id pivot column)
- Methods:
  - `hasPermission(string $permissionSlug): bool` — checks all user roles in current company, collects their permissions, returns true if slug found
  - `hasRole(string $roleSlug): bool` — checks role_user for current company
  - `getRolesForCompany(?int $companyId = null): Collection` — returns roles for given company (or current)
  - `switchToCompany(Company $company): void`
- Accessor: `full_name`
- Boot: auto-generate matricule on creating
- **NO is_superadmin column** — Super Admin is just a role with all permissions assigned

### Service: `AuthService`
- `register(array $data): User`
- `login(array $credentials): array` — returns user + token
- `logout(User $user): void`
- `refreshToken(User $user): string`
- `changePassword(User $user, string $old, string $new): bool`
- `forgotPassword(string $email): void`
- `resetPassword(array $data): bool`

### Controller: `AuthController`
- `register`, `login`, `logout`, `me`, `refreshToken`, `changePassword`, `forgotPassword`, `resetPassword`
- All through `AuthService`

### Middleware: `SetCurrentCompany`
- Reads `X-Company-Id` header or `current_company_id` from user
- Sets company context for BelongsToCompany trait

### Middleware: `CheckPermission`
- `CheckPermission:permission_name` — checks user has permission in current company context

### FormRequests
- `LoginRequest` — email required|email, password required
- `RegisterRequest` — first_name, last_name, email unique, password confirmed min:8
- `ChangePasswordRequest` — current_password, new_password confirmed min:8

### Routes (prefix: `/auth`)
- `POST /register`
- `POST /login`
- `POST /logout` (auth:sanctum)
- `GET /me` (auth:sanctum)
- `POST /refresh-token` (auth:sanctum)
- `POST /change-password` (auth:sanctum)
- `POST /forgot-password`
- `POST /reset-password`

### Security Rules
- Rate limit: 5 login attempts per minute
- Password: min 8 chars, mixed case, number, symbol
- Sanctum tokens expire after 24h (configurable)
- Force HTTPS in production
- CSRF protection on web routes
- XSS sanitization on all inputs

### Tests
- Feature: register, login, logout, token refresh, password change, rate limiting
- Unit: AuthService, matricule generation, permission checks

---

## TASK 4: Roles & Permissions Module (3-Table RBAC)

### Description
Simple 3-table RBAC: `users` ←→ `roles` (M2M) ←→ `permissions` (M2M). NO direct user permission overrides. Permissions are ONLY assigned to roles. Users get permissions through their roles. Roles are company-scoped via the `role_user` pivot.

### Migration: `roles`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id, nullable (null = global) |
| name | string(100) | not null |
| slug | string(100) | not null |
| description | text | nullable |
| is_system | boolean | default false |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (company_id, slug)

### Migration: `permissions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| module | string(100) | not null |
| name | string(100) | not null |
| slug | string(200) | unique, not null |
| description | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Slug pattern**: `{module}.{action}` e.g. `invoices.create`

### Migration: `permission_role` (pivot — which permissions belong to which role)

| Column | Type | Constraints |
|--------|------|-------------|
| role_id | bigint | FK -> roles.id, cascade delete |
| permission_id | bigint | FK -> permissions.id, cascade delete |

**Primary key**: (role_id, permission_id)

### Migration: `role_user` (pivot — which roles a user has, per company)

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| role_id | bigint | FK -> roles.id, cascade delete |
| user_id | bigint | FK -> users.id, cascade delete |
| company_id | bigint | FK -> companies.id, cascade delete |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (role_id, user_id, company_id)

This design means a user can have **different roles in different companies**. For example, user X can be Admin in Company A and Viewer in Company B.

### Model: `Role`
- Relationships: `permissions()` BelongsToMany, `users()` BelongsToMany (with company_id pivot)
- Scopes: `forCompany($companyId)`, `global()` (where company_id is null)

### Model: `Permission`
- Relationships: `roles()` BelongsToMany

### Permission Check Flow (in User model)

```php
// User.php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'role_user')
        ->withPivot('company_id')
        ->withTimestamps();
}

public function hasPermission(string $permissionSlug): bool
{
    return $this->roles()
        ->wherePivot('company_id', $this->current_company_id)
        ->whereHas('permissions', fn($q) => $q->where('slug', $permissionSlug))
        ->exists();
}

public function hasRole(string $roleSlug): bool
{
    return $this->roles()
        ->wherePivot('company_id', $this->current_company_id)
        ->where('slug', $roleSlug)
        ->exists();
}

public function getRolesForCompany(?int $companyId = null): Collection
{
    $companyId = $companyId ?? $this->current_company_id;
    return $this->roles()->wherePivot('company_id', $companyId)->get();
}
```

### CheckPermission Middleware

```php
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

// Usage in routes:
Route::middleware(['auth:sanctum', 'company', 'permission:invoices.create'])
    ->post('/invoices', [InvoiceController::class, 'store']);

// Usage in Blade views:
@if(auth()->user()->hasPermission('invoices.create'))
    <a href="{{ route('invoices.create') }}">Nouvelle Facture</a>
@endif
```

### Service: `RoleService`
- `create(array $data): Role`
- `update(Role $role, array $data): Role`
- `delete(Role $role): bool`
- `assignPermissions(Role $role, array $permissionIds): void`
- `revokePermissions(Role $role, array $permissionIds): void`
- `syncPermissions(Role $role, array $permissionIds): void`
- `assignToUser(Role $role, User $user, Company $company): void`
- `removeFromUser(Role $role, User $user, Company $company): void`

### Service: `PermissionService`
- `seedAllPermissions(): void` — generates all module.action combos
- `getByModule(string $module): Collection`
- `getAllGroupedByModule(): array` — returns ['module' => [permissions]]
- **NO userCan()** — permission check logic lives in `User::hasPermission()`

### Seeder: `PermissionSeeder`
Generates permissions for EVERY module with these actions:
- `view`, `view_any`, `create`, `update`, `delete`, `restore`, `force_delete`, `export`, `import`, `print`

### Default Roles (seeded)
1. **Super Admin** — all permissions (system role, is_system=true). This is a ROLE, not a user flag.
2. **Admin** — all except force_delete, system settings
3. **Manager** — view_any, view, create, update, export, print per module
4. **User** — view_any, view, create (own), update (own)
5. **Viewer** — view_any, view only

### Tests
- Feature: CRUD roles, assign/revoke permissions, assign/remove role to user per company
- Feature: CheckPermission middleware — user with permission → 200, without → 403, multiple roles merged
- Unit: User::hasPermission() with single role, multiple roles, cross-company isolation
- Unit: PermissionService seeder completeness

## TASK 5: Personnel Module

### Description
Personnel records linked to users via matricule. Company-scoped. Manages departments, positions, contracts, leaves, attendance.

### Migration: `departments`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(255) | not null |
| code | string(50) | nullable |
| parent_id | bigint | FK -> departments.id, nullable (self-ref for hierarchy) |
| manager_id | bigint | FK -> personnels.id, nullable |
| description | text | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `positions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(255) | not null |
| code | string(50) | nullable |
| department_id | bigint | FK -> departments.id, nullable |
| description | text | nullable |
| min_salary | decimal(15,2) | nullable |
| max_salary | decimal(15,2) | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `personnels`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| user_id | bigint | FK -> users.id, nullable |
| matricule | string(50) | not null |
| first_name | string(100) | not null |
| last_name | string(100) | not null |
| email | string(255) | nullable |
| phone | string(50) | nullable |
| date_of_birth | date | nullable |
| gender | enum('male','female','other') | nullable |
| national_id | string(100) | nullable |
| social_security_number | string(100) | nullable |
| marital_status | enum('single','married','divorced','widowed') | nullable |
| address | text | nullable |
| city | string(100) | nullable |
| state | string(100) | nullable |
| zip_code | string(20) | nullable |
| country | string(100) | nullable |
| department_id | bigint | FK -> departments.id, nullable |
| position_id | bigint | FK -> positions.id, nullable |
| hire_date | date | not null |
| termination_date | date | nullable |
| employment_type | enum('full_time','part_time','contract','intern','freelance') | default 'full_time' |
| base_salary | decimal(15,2) | nullable |
| bank_name | string(255) | nullable |
| bank_account | string(100) | nullable |
| emergency_contact_name | string(255) | nullable |
| emergency_contact_phone | string(50) | nullable |
| photo_path | string(500) | nullable |
| status | enum('active','on_leave','suspended','terminated') | default 'active' |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

**Unique constraint**: (company_id, matricule)

### Migration: `contracts`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| personnel_id | bigint | FK -> personnels.id |
| reference | string(100) | unique per company |
| type | enum('cdi','cdd','stage','freelance','interim') | not null |
| start_date | date | not null |
| end_date | date | nullable |
| salary | decimal(15,2) | not null |
| salary_type | enum('monthly','daily','hourly','annual') | default 'monthly' |
| trial_period_end | date | nullable |
| working_hours_per_week | decimal(5,2) | default 40 |
| benefits | jsonb | nullable |
| document_path | string(500) | nullable |
| status | enum('draft','active','expired','terminated','renewed') | default 'draft' |
| signed_at | timestamp | nullable |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `leaves`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| personnel_id | bigint | FK -> personnels.id |
| type | enum('annual','sick','maternity','paternity','unpaid','compensatory','other') | not null |
| start_date | date | not null |
| end_date | date | not null |
| days_count | decimal(5,2) | not null |
| reason | text | nullable |
| status | enum('pending','approved','rejected','cancelled') | default 'pending' |
| approved_by | bigint | FK -> users.id, nullable |
| approved_at | timestamp | nullable |
| rejection_reason | text | nullable |
| document_path | string(500) | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `attendances`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| personnel_id | bigint | FK -> personnels.id |
| date | date | not null |
| check_in | timestamp | nullable |
| check_out | timestamp | nullable |
| total_hours | decimal(5,2) | nullable |
| overtime_hours | decimal(5,2) | default 0 |
| status | enum('present','absent','late','half_day','remote','holiday') | default 'present' |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (company_id, personnel_id, date)

### Services
- `PersonnelService` — CRUD, link to user, generate matricule, terminate, status change
- `DepartmentService` — CRUD with hierarchy
- `PositionService` — CRUD
- `ContractService` — CRUD, renew, terminate, check expiry
- `LeaveService` — request, approve, reject, cancel, balance calculation
- `AttendanceService` — check in/out, report generation

### Controllers
- `PersonnelController` — CRUD + link/unlink user + terminate
- `DepartmentController` — CRUD
- `PositionController` — CRUD
- `ContractController` — CRUD + renew + terminate
- `LeaveController` — CRUD + approve + reject
- `AttendanceController` — CRUD + checkIn + checkOut + report

### Routes (all prefixed `/api/{company}`, auth:sanctum, company middleware)

**Personnel**: `/personnels`
- GET `/` — index (filterable by department, position, status)
- POST `/` — store
- GET `/{personnel}` — show
- PUT `/{personnel}` — update
- DELETE `/{personnel}` — destroy
- POST `/{personnel}/link-user` — link to user account
- POST `/{personnel}/terminate` — terminate

**Departments**: `/departments`
- Full CRUD + GET `/{department}/tree` (hierarchy)

**Positions**: `/positions` — Full CRUD

**Contracts**: `/contracts`
- Full CRUD + POST `/{contract}/renew` + POST `/{contract}/terminate`

**Leaves**: `/leaves`
- Full CRUD + POST `/{leave}/approve` + POST `/{leave}/reject` + GET `/balance/{personnel}`

**Attendances**: `/attendances`
- Full CRUD + POST `/check-in` + POST `/check-out` + GET `/report`

### Tests
- Feature: Full CRUD for each sub-module, personnel-user linking, leave approval flow, attendance check-in/out
- Unit: Matricule generation, leave balance calculation, contract expiry check, attendance hours calculation

---

## TASK 6: Sales Module — Customers

### Description
Customer management, company-scoped.

### Migration: `customers`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| code | string(50) | not null |
| type | enum('individual','company') | default 'company' |
| name | string(255) | not null |
| legal_name | string(255) | nullable |
| tax_id | string(100) | nullable |
| ice | string(100) | nullable |
| email | string(255) | nullable |
| phone | string(50) | nullable |
| fax | string(50) | nullable |
| website | string(255) | nullable |
| address | text | nullable |
| city | string(100) | nullable |
| state | string(100) | nullable |
| zip_code | string(20) | nullable |
| country | string(100) | default 'Morocco' |
| contact_person | string(255) | nullable |
| contact_phone | string(50) | nullable |
| contact_email | string(255) | nullable |
| payment_term_id | bigint | FK -> payment_terms.id, nullable |
| credit_limit | decimal(15,2) | default 0 |
| balance | decimal(15,2) | default 0 |
| notes | text | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

**Unique constraint**: (company_id, code)

### Service: `CustomerService`
- CRUD, search, balance update, credit check

### Routes: `/customers` — Full CRUD + GET `/search?q=`

---

## TASK 7: Sales Module — Quotes (Devis)

### Description
Sales quotations with line items. Can be converted to sales orders.

### Migration: `quotes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| customer_id | bigint | FK -> customers.id |
| date | date | not null |
| validity_date | date | nullable |
| currency_code | string(3) | default 'MAD' |
| exchange_rate | decimal(10,4) | default 1 |
| subtotal_ht | decimal(15,2) | default 0 |
| total_tax | decimal(15,2) | default 0 |
| total_discount | decimal(15,2) | default 0 |
| total_ttc | decimal(15,2) | default 0 |
| discount_type | enum('percentage','fixed') | nullable |
| discount_value | decimal(15,2) | default 0 |
| notes | text | nullable |
| internal_notes | text | nullable |
| terms_conditions | text | nullable |
| status | enum('draft','sent','accepted','rejected','expired','converted') | default 'draft' |
| sent_at | timestamp | nullable |
| accepted_at | timestamp | nullable |
| rejected_at | timestamp | nullable |
| converted_to_order_id | bigint | nullable |
| created_by | bigint | FK -> users.id |
| updated_by | bigint | FK -> users.id, nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

**Unique constraint**: (company_id, reference)

### Migration: `quote_lines`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| quote_id | bigint | FK -> quotes.id, cascade delete |
| product_id | bigint | FK -> products.id, nullable |
| description | text | not null |
| quantity | decimal(15,3) | not null |
| unit | string(50) | default 'unit' |
| unit_price_ht | decimal(15,2) | not null |
| discount_type | enum('percentage','fixed') | nullable |
| discount_value | decimal(15,2) | default 0 |
| tax_id | bigint | FK -> taxes.id, nullable |
| tax_rate | decimal(5,2) | default 0 |
| subtotal_ht | decimal(15,2) | not null |
| tax_amount | decimal(15,2) | default 0 |
| total_ttc | decimal(15,2) | not null |
| sort_order | integer | default 0 |
| created_at | timestamp | |
| updated_at | timestamp | |

### Service: `QuoteService`
- `create(array $data): Quote` — with lines
- `update(Quote $quote, array $data): Quote` — with lines
- `delete(Quote $quote): bool`
- `send(Quote $quote): Quote` — mark as sent
- `accept(Quote $quote): Quote`
- `reject(Quote $quote): Quote`
- `duplicate(Quote $quote): Quote`
- `convertToOrder(Quote $quote): SalesOrder`
- `calculateTotals(Quote $quote): void` — recalculate all line totals
- `generatePdf(Quote $quote): string` — PDF path

### Controller: `QuoteController`
- CRUD + send + accept + reject + duplicate + convertToOrder + pdf

### Routes: `/quotes`
- Full CRUD
- POST `/{quote}/send`
- POST `/{quote}/accept`
- POST `/{quote}/reject`
- POST `/{quote}/duplicate`
- POST `/{quote}/convert-to-order`
- GET `/{quote}/pdf`

### Tests
- Feature: Full lifecycle (draft → sent → accepted → converted), line calculations, PDF generation
- Unit: Total calculation, tax computation, reference generation

---

## TASK 8: Sales Module — Sales Orders (Bons de Commande)

### Description
Sales orders with lines. Can originate from quotes. Can generate invoices and delivery notes.

### Migration: `sales_orders`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| quote_id | bigint | FK -> quotes.id, nullable |
| customer_id | bigint | FK -> customers.id |
| date | date | not null |
| expected_delivery_date | date | nullable |
| currency_code | string(3) | default 'MAD' |
| exchange_rate | decimal(10,4) | default 1 |
| subtotal_ht | decimal(15,2) | default 0 |
| total_tax | decimal(15,2) | default 0 |
| total_discount | decimal(15,2) | default 0 |
| total_ttc | decimal(15,2) | default 0 |
| discount_type | enum('percentage','fixed') | nullable |
| discount_value | decimal(15,2) | default 0 |
| shipping_address | text | nullable |
| notes | text | nullable |
| internal_notes | text | nullable |
| status | enum('draft','confirmed','in_progress','delivered','invoiced','cancelled') | default 'draft' |
| confirmed_at | timestamp | nullable |
| created_by | bigint | FK -> users.id |
| updated_by | bigint | FK -> users.id, nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `sales_order_lines`

Same structure as `quote_lines` but with `sales_order_id` FK and additional:

| Column | Type | Constraints |
|--------|------|-------------|
| delivered_quantity | decimal(15,3) | default 0 |
| invoiced_quantity | decimal(15,3) | default 0 |

### Service: `SalesOrderService`
- CRUD with lines, confirm, cancel, duplicate
- `generateInvoice(SalesOrder $order): Invoice`
- `generateDeliveryNote(SalesOrder $order): DeliveryNote`

### Routes: `/sales-orders`
- Full CRUD
- POST `/{order}/confirm`
- POST `/{order}/cancel`
- POST `/{order}/generate-invoice`
- POST `/{order}/generate-delivery-note`
- GET `/{order}/pdf`

---

## TASK 9: Sales Module — Invoices (Factures)

### Description
Sales invoices with lines. Support for partial payments, credit notes.

### Migration: `invoices`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| sales_order_id | bigint | FK -> sales_orders.id, nullable |
| customer_id | bigint | FK -> customers.id |
| date | date | not null |
| due_date | date | not null |
| currency_code | string(3) | default 'MAD' |
| exchange_rate | decimal(10,4) | default 1 |
| subtotal_ht | decimal(15,2) | default 0 |
| total_tax | decimal(15,2) | default 0 |
| total_discount | decimal(15,2) | default 0 |
| total_ttc | decimal(15,2) | default 0 |
| amount_paid | decimal(15,2) | default 0 |
| amount_due | decimal(15,2) | default 0 |
| discount_type | enum('percentage','fixed') | nullable |
| discount_value | decimal(15,2) | default 0 |
| payment_term_id | bigint | FK -> payment_terms.id, nullable |
| notes | text | nullable |
| internal_notes | text | nullable |
| terms_conditions | text | nullable |
| status | enum('draft','sent','partial','paid','overdue','cancelled','refunded') | default 'draft' |
| sent_at | timestamp | nullable |
| paid_at | timestamp | nullable |
| cancelled_at | timestamp | nullable |
| cancellation_reason | text | nullable |
| created_by | bigint | FK -> users.id |
| updated_by | bigint | FK -> users.id, nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `invoice_lines`

Same pattern as quote_lines with `invoice_id` FK.

### Service: `InvoiceService`
- CRUD with lines, send, cancel, markAsPaid
- `recordPayment(Invoice $invoice, array $paymentData): Payment`
- `createCreditNote(Invoice $invoice, array $data): CreditNote`
- `calculateAmountDue(Invoice $invoice): void`
- `checkOverdue(): void` — batch job
- `generatePdf(Invoice $invoice): string`

### Routes: `/invoices`
- Full CRUD
- POST `/{invoice}/send`
- POST `/{invoice}/cancel`
- POST `/{invoice}/record-payment`
- POST `/{invoice}/credit-note`
- GET `/{invoice}/pdf`
- GET `/overdue` — list overdue invoices

---

## TASK 10: Sales Module — Credit Notes & Delivery Notes

### Migration: `credit_notes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| invoice_id | bigint | FK -> invoices.id |
| customer_id | bigint | FK -> customers.id |
| date | date | not null |
| subtotal_ht | decimal(15,2) | default 0 |
| total_tax | decimal(15,2) | default 0 |
| total_ttc | decimal(15,2) | default 0 |
| reason | text | not null |
| status | enum('draft','confirmed','applied','cancelled') | default 'draft' |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `credit_note_lines` — Same pattern as invoice_lines with `credit_note_id`

### Migration: `delivery_notes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| sales_order_id | bigint | FK -> sales_orders.id |
| customer_id | bigint | FK -> customers.id |
| date | date | not null |
| shipping_address | text | nullable |
| carrier | string(255) | nullable |
| tracking_number | string(255) | nullable |
| weight | decimal(10,2) | nullable |
| notes | text | nullable |
| status | enum('draft','ready','shipped','delivered','returned') | default 'draft' |
| shipped_at | timestamp | nullable |
| delivered_at | timestamp | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `delivery_note_lines`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| delivery_note_id | bigint | FK -> delivery_notes.id, cascade |
| sales_order_line_id | bigint | FK -> sales_order_lines.id, nullable |
| product_id | bigint | FK -> products.id, nullable |
| description | text | not null |
| quantity | decimal(15,3) | not null |
| unit | string(50) | default 'unit' |
| sort_order | integer | default 0 |
| created_at | timestamp | |
| updated_at | timestamp | |

### Services
- `CreditNoteService` — CRUD, confirm, apply to invoice balance
- `DeliveryNoteService` — CRUD, ship, deliver, return, update order delivered qty

### Routes
- `/credit-notes` — Full CRUD + POST `/{creditNote}/confirm` + POST `/{creditNote}/apply`
- `/delivery-notes` — Full CRUD + POST `/{deliveryNote}/ship` + POST `/{deliveryNote}/deliver`

---

## TASK 11: Purchasing Module — Suppliers

### Migration: `suppliers`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| code | string(50) | not null |
| type | enum('individual','company') | default 'company' |
| name | string(255) | not null |
| legal_name | string(255) | nullable |
| tax_id | string(100) | nullable |
| ice | string(100) | nullable |
| email | string(255) | nullable |
| phone | string(50) | nullable |
| fax | string(50) | nullable |
| website | string(255) | nullable |
| address | text | nullable |
| city | string(100) | nullable |
| state | string(100) | nullable |
| zip_code | string(20) | nullable |
| country | string(100) | default 'Morocco' |
| contact_person | string(255) | nullable |
| contact_phone | string(50) | nullable |
| contact_email | string(255) | nullable |
| payment_term_id | bigint | FK -> payment_terms.id, nullable |
| bank_name | string(255) | nullable |
| bank_account | string(100) | nullable |
| balance | decimal(15,2) | default 0 |
| notes | text | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Service: `SupplierService` — CRUD, search, balance management
### Routes: `/suppliers` — Full CRUD + search

---

## TASK 12: Purchasing Module — Purchase Requests, Orders, Receptions, Invoices

### Migration: `purchase_requests`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| requested_by | bigint | FK -> users.id |
| department_id | bigint | FK -> departments.id, nullable |
| date | date | not null |
| needed_by_date | date | nullable |
| priority | enum('low','medium','high','urgent') | default 'medium' |
| reason | text | nullable |
| status | enum('draft','submitted','approved','rejected','converted','cancelled') | default 'draft' |
| approved_by | bigint | FK -> users.id, nullable |
| approved_at | timestamp | nullable |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `purchase_request_lines`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| purchase_request_id | bigint | FK cascade |
| product_id | bigint | FK -> products.id, nullable |
| description | text | not null |
| quantity | decimal(15,3) | not null |
| unit | string(50) | default 'unit' |
| estimated_unit_price | decimal(15,2) | nullable |
| sort_order | integer | default 0 |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `purchase_orders`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| purchase_request_id | bigint | FK nullable |
| supplier_id | bigint | FK -> suppliers.id |
| date | date | not null |
| expected_delivery_date | date | nullable |
| currency_code | string(3) | default 'MAD' |
| exchange_rate | decimal(10,4) | default 1 |
| subtotal_ht | decimal(15,2) | default 0 |
| total_tax | decimal(15,2) | default 0 |
| total_discount | decimal(15,2) | default 0 |
| total_ttc | decimal(15,2) | default 0 |
| discount_type | enum('percentage','fixed') | nullable |
| discount_value | decimal(15,2) | default 0 |
| shipping_address | text | nullable |
| notes | text | nullable |
| internal_notes | text | nullable |
| status | enum('draft','sent','confirmed','partial_received','received','invoiced','cancelled') | default 'draft' |
| sent_at | timestamp | nullable |
| confirmed_at | timestamp | nullable |
| created_by | bigint | FK -> users.id |
| updated_by | bigint | FK -> users.id, nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `purchase_order_lines`

Same pattern as sales_order_lines with `purchase_order_id` FK + `received_quantity` + `invoiced_quantity`.

### Migration: `reception_notes` (Bon de Réception)

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| purchase_order_id | bigint | FK -> purchase_orders.id |
| supplier_id | bigint | FK -> suppliers.id |
| date | date | not null |
| warehouse_id | bigint | FK -> warehouses.id, nullable |
| delivery_note_ref | string(255) | nullable (supplier's BL number) |
| notes | text | nullable |
| status | enum('draft','confirmed','cancelled') | default 'draft' |
| confirmed_at | timestamp | nullable |
| confirmed_by | bigint | FK -> users.id, nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `reception_note_lines`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| reception_note_id | bigint | FK cascade |
| purchase_order_line_id | bigint | FK nullable |
| product_id | bigint | FK -> products.id, nullable |
| description | text | not null |
| ordered_quantity | decimal(15,3) | default 0 |
| received_quantity | decimal(15,3) | not null |
| rejected_quantity | decimal(15,3) | default 0 |
| unit | string(50) | default 'unit' |
| rejection_reason | text | nullable |
| sort_order | integer | default 0 |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `purchase_invoices`

Same structure as `invoices` but with `supplier_id` instead of `customer_id`, `purchase_order_id` FK.

### Migration: `purchase_invoice_lines` — Same pattern

### Services
- `PurchaseRequestService` — CRUD, submit, approve, reject, convert to PO
- `PurchaseOrderService` — CRUD with lines, send, confirm, cancel, generate reception note, generate purchase invoice
- `ReceptionNoteService` — CRUD, confirm (triggers stock movement), cancel
- `PurchaseInvoiceService` — CRUD, record payment, mark paid

### Routes
- `/purchase-requests` — Full CRUD + submit + approve + reject + convert-to-order
- `/purchase-orders` — Full CRUD + send + confirm + cancel + generate-reception + generate-invoice + pdf
- `/reception-notes` — Full CRUD + confirm + cancel
- `/purchase-invoices` — Full CRUD + record-payment + pdf

### Tests
- Feature: Full purchase cycle (request → approve → PO → reception → invoice → payment)
- Unit: Quantity tracking, totals, reception stock impact

## TASK 13: Inventory Module

### Description
Product catalog, categories, warehouses, stock movements, physical inventory.

### Migration: `product_categories`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(255) | not null |
| code | string(50) | nullable |
| parent_id | bigint | FK self-ref, nullable |
| description | text | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `products`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| code | string(100) | not null |
| barcode | string(100) | nullable |
| name | string(255) | not null |
| description | text | nullable |
| category_id | bigint | FK -> product_categories.id, nullable |
| type | enum('product','service','consumable') | default 'product' |
| unit | string(50) | default 'unit' |
| purchase_price | decimal(15,2) | default 0 |
| sale_price | decimal(15,2) | default 0 |
| cost_price | decimal(15,2) | default 0 |
| tax_id | bigint | FK -> taxes.id, nullable |
| purchase_tax_id | bigint | FK -> taxes.id, nullable |
| min_stock_level | decimal(15,3) | default 0 |
| max_stock_level | decimal(15,3) | default 0 |
| reorder_point | decimal(15,3) | default 0 |
| weight | decimal(10,3) | nullable |
| dimensions | jsonb | nullable |
| image_path | string(500) | nullable |
| is_active | boolean | default true |
| is_purchasable | boolean | default true |
| is_saleable | boolean | default true |
| is_stockable | boolean | default true |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

**Unique constraint**: (company_id, code)

### Migration: `warehouses`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(255) | not null |
| code | string(50) | not null |
| address | text | nullable |
| city | string(100) | nullable |
| manager_id | bigint | FK -> users.id, nullable |
| is_default | boolean | default false |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `stock_movements`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| product_id | bigint | FK -> products.id |
| warehouse_id | bigint | FK -> warehouses.id |
| type | enum('in','out','transfer','adjustment','return','initial') | not null |
| quantity | decimal(15,3) | not null |
| unit_cost | decimal(15,2) | nullable |
| source_type | string(255) | nullable (polymorphic: ReceptionNote, DeliveryNote, etc.) |
| source_id | bigint | nullable |
| destination_warehouse_id | bigint | FK -> warehouses.id, nullable (for transfers) |
| reason | text | nullable |
| date | date | not null |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `stock_levels` (computed/cached table)

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| product_id | bigint | FK -> products.id |
| warehouse_id | bigint | FK -> warehouses.id |
| quantity_on_hand | decimal(15,3) | default 0 |
| quantity_reserved | decimal(15,3) | default 0 |
| quantity_available | decimal(15,3) | default 0 |
| last_movement_at | timestamp | nullable |
| updated_at | timestamp | |

**Unique constraint**: (company_id, product_id, warehouse_id)

### Migration: `stock_inventories` (Physical inventory count)

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| warehouse_id | bigint | FK -> warehouses.id |
| date | date | not null |
| notes | text | nullable |
| status | enum('draft','in_progress','validated','cancelled') | default 'draft' |
| validated_by | bigint | FK -> users.id, nullable |
| validated_at | timestamp | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `stock_inventory_lines`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| stock_inventory_id | bigint | FK cascade |
| product_id | bigint | FK -> products.id |
| theoretical_quantity | decimal(15,3) | not null |
| counted_quantity | decimal(15,3) | nullable |
| difference | decimal(15,3) | default 0 |
| unit_cost | decimal(15,2) | nullable |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Services
- `ProductService` — CRUD, search, stock level check, low stock alert
- `ProductCategoryService` — CRUD with hierarchy
- `WarehouseService` — CRUD, default warehouse
- `StockMovementService` — create movement, update stock levels, transfer between warehouses
- `StockInventoryService` — CRUD, validate (generates adjustment movements), report

### Routes
- `/products` — Full CRUD + GET `/low-stock` + GET `/{product}/stock-levels`
- `/product-categories` — Full CRUD + GET `/{category}/tree`
- `/warehouses` — Full CRUD
- `/stock-movements` — Full CRUD + POST `/transfer` + GET `/report`
- `/stock-inventories` — Full CRUD + POST `/{inventory}/start` + POST `/{inventory}/validate`

### Tests
- Feature: Product CRUD, stock in/out, transfer, inventory count → adjustment
- Unit: Stock level calculation, low stock detection, movement creation

---

## TASK 14: Finance Module

### Description
Chart of accounts, journal entries, bank accounts, payments, taxes, currencies, payment terms.

### Migration: `currencies`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| code | string(3) | unique, not null |
| name | string(100) | not null |
| symbol | string(10) | not null |
| decimal_places | integer | default 2 |
| exchange_rate | decimal(10,6) | default 1 |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `taxes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(100) | not null |
| code | string(50) | not null |
| rate | decimal(5,2) | not null |
| type | enum('percentage','fixed') | default 'percentage' |
| is_inclusive | boolean | default false |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `payment_terms`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(100) | not null |
| days | integer | not null |
| description | text | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `payment_methods`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(100) | not null |
| code | string(50) | not null |
| type | enum('cash','check','bank_transfer','credit_card','mobile_payment','other') | not null |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `chart_of_accounts`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| code | string(50) | not null |
| name | string(255) | not null |
| type | enum('asset','liability','equity','revenue','expense') | not null |
| parent_id | bigint | FK self-ref, nullable |
| description | text | nullable |
| is_active | boolean | default true |
| balance | decimal(15,2) | default 0 |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (company_id, code)

### Migration: `bank_accounts`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(255) | not null |
| bank_name | string(255) | not null |
| account_number | string(100) | not null |
| iban | string(50) | nullable |
| swift | string(20) | nullable |
| currency_code | string(3) | default 'MAD' |
| balance | decimal(15,2) | default 0 |
| account_id | bigint | FK -> chart_of_accounts.id, nullable |
| is_default | boolean | default false |
| is_active | boolean | default true |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `journal_entries`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| date | date | not null |
| description | text | nullable |
| source_type | string(255) | nullable (polymorphic) |
| source_id | bigint | nullable |
| status | enum('draft','posted','cancelled') | default 'draft' |
| posted_by | bigint | FK -> users.id, nullable |
| posted_at | timestamp | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `journal_entry_lines`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| journal_entry_id | bigint | FK cascade |
| account_id | bigint | FK -> chart_of_accounts.id |
| debit | decimal(15,2) | default 0 |
| credit | decimal(15,2) | default 0 |
| description | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Validation**: Total debits must equal total credits per journal entry.

### Migration: `payments`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| type | enum('incoming','outgoing') | not null |
| payable_type | string(255) | not null (polymorphic: Invoice, PurchaseInvoice) |
| payable_id | bigint | not null |
| partner_type | enum('customer','supplier') | not null |
| partner_id | bigint | not null |
| amount | decimal(15,2) | not null |
| currency_code | string(3) | default 'MAD' |
| payment_method_id | bigint | FK -> payment_methods.id |
| bank_account_id | bigint | FK -> bank_accounts.id, nullable |
| payment_date | date | not null |
| check_number | string(100) | nullable |
| transaction_reference | string(255) | nullable |
| notes | text | nullable |
| status | enum('draft','confirmed','cancelled') | default 'draft' |
| confirmed_by | bigint | FK -> users.id, nullable |
| confirmed_at | timestamp | nullable |
| journal_entry_id | bigint | FK -> journal_entries.id, nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Services
- `CurrencyService` — CRUD, exchange rate update
- `TaxService` — CRUD, calculate tax
- `PaymentTermService` — CRUD
- `PaymentMethodService` — CRUD
- `ChartOfAccountService` — CRUD with hierarchy, balance calculation
- `BankAccountService` — CRUD, balance update
- `JournalEntryService` — CRUD, post, cancel, validate debit=credit
- `PaymentService` — CRUD, confirm (creates journal entry, updates invoice/bank)

### Routes
- `/currencies` — Full CRUD
- `/taxes` — Full CRUD
- `/payment-terms` — Full CRUD
- `/payment-methods` — Full CRUD
- `/chart-of-accounts` — Full CRUD + GET `/tree` + GET `/{account}/balance`
- `/bank-accounts` — Full CRUD
- `/journal-entries` — Full CRUD + POST `/{entry}/post` + POST `/{entry}/cancel`
- `/payments` — Full CRUD + POST `/{payment}/confirm` + POST `/{payment}/cancel`

### Tests
- Feature: Payment full flow, journal entry posting, debit=credit validation
- Unit: Tax calculation, balance computation, journal validation

---

## TASK 15: Caution (Deposit/Guarantee) Module

### Description
Manage caution/deposit tracking for contracts, rentals, or projects.

### Migration: `caution_types`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(255) | not null |
| code | string(50) | not null |
| description | text | nullable |
| default_percentage | decimal(5,2) | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `cautions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| caution_type_id | bigint | FK -> caution_types.id |
| direction | enum('given','received') | not null |
| partner_type | enum('customer','supplier','other') | not null |
| partner_id | bigint | not null |
| partner_name | string(255) | not null |
| related_type | string(255) | nullable (polymorphic: Contract, PurchaseOrder, SalesOrder, Project) |
| related_id | bigint | nullable |
| amount | decimal(15,2) | not null |
| currency_code | string(3) | default 'MAD' |
| issue_date | date | not null |
| expiry_date | date | nullable |
| return_date | date | nullable |
| bank_name | string(255) | nullable |
| bank_reference | string(255) | nullable |
| document_path | string(500) | nullable |
| notes | text | nullable |
| status | enum('draft','active','partially_returned','returned','expired','forfeited','cancelled') | default 'draft' |
| returned_amount | decimal(15,2) | default 0 |
| forfeited_amount | decimal(15,2) | default 0 |
| activated_at | timestamp | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `caution_histories`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| caution_id | bigint | FK -> cautions.id, cascade |
| action | enum('created','activated','partial_return','full_return','extended','forfeited','cancelled') | not null |
| amount | decimal(15,2) | nullable |
| previous_status | string(50) | nullable |
| new_status | string(50) | not null |
| notes | text | nullable |
| performed_by | bigint | FK -> users.id |
| performed_at | timestamp | not null |
| created_at | timestamp | |

### Service: `CautionService`
- `create(array $data): Caution`
- `update(Caution $caution, array $data): Caution`
- `delete(Caution $caution): bool`
- `activate(Caution $caution): Caution`
- `partialReturn(Caution $caution, float $amount, ?string $notes): Caution`
- `fullReturn(Caution $caution, ?string $notes): Caution`
- `extend(Caution $caution, string $newExpiryDate): Caution`
- `forfeit(Caution $caution, float $amount, ?string $notes): Caution`
- `cancel(Caution $caution, ?string $notes): Caution`
- `getExpiring(int $daysAhead = 30): Collection` — alert for expiring cautions
- `getByPartner(string $partnerType, int $partnerId): Collection`
- `getDashboardStats(): array` — totals given/received, active count

### Service: `CautionTypeService` — CRUD

### Controller: `CautionController`
- Full CRUD + activate + partialReturn + fullReturn + extend + forfeit + cancel
- GET `/expiring` — list expiring cautions
- GET `/stats` — dashboard stats

### Routes: `/cautions`
- Full CRUD
- POST `/{caution}/activate`
- POST `/{caution}/partial-return`
- POST `/{caution}/full-return`
- POST `/{caution}/extend`
- POST `/{caution}/forfeit`
- POST `/{caution}/cancel`
- GET `/expiring`
- GET `/stats`

### Routes: `/caution-types` — Full CRUD

### Tests
- Feature: Full lifecycle (draft → active → partial return → full return), expiring alerts, stats
- Unit: Amount tracking, status transitions, history logging

## TASK 16: Events Module

### Description
Company events management with categories, participants, and calendar integration.

### Migration: `event_categories`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| name | string(255) | not null |
| color | string(7) | nullable (hex color) |
| description | text | nullable |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `events`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| title | string(255) | not null |
| description | text | nullable |
| category_id | bigint | FK -> event_categories.id, nullable |
| type | enum('meeting','conference','training','workshop','social','holiday','other') | default 'meeting' |
| location | string(500) | nullable |
| start_date | timestamp | not null |
| end_date | timestamp | not null |
| is_all_day | boolean | default false |
| is_recurring | boolean | default false |
| recurrence_pattern | jsonb | nullable |
| max_participants | integer | nullable |
| is_mandatory | boolean | default false |
| budget | decimal(15,2) | nullable |
| actual_cost | decimal(15,2) | nullable |
| notes | text | nullable |
| status | enum('planned','confirmed','in_progress','completed','cancelled','postponed') | default 'planned' |
| cancelled_reason | text | nullable |
| organizer_id | bigint | FK -> users.id |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `event_participants`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| event_id | bigint | FK -> events.id, cascade |
| personnel_id | bigint | FK -> personnels.id, nullable |
| user_id | bigint | FK -> users.id, nullable |
| external_name | string(255) | nullable |
| external_email | string(255) | nullable |
| role | enum('organizer','speaker','attendee','guest') | default 'attendee' |
| status | enum('invited','confirmed','declined','tentative','attended','no_show') | default 'invited' |
| response_at | timestamp | nullable |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Services
- `EventService` — CRUD, confirm, cancel, postpone, complete, duplicate, getUpcoming, getByDateRange
- `EventCategoryService` — CRUD
- `EventParticipantService` — invite, confirm, decline, bulkInvite, getAttendance

### Routes
- `/events` — Full CRUD + POST `/{event}/confirm` + POST `/{event}/cancel` + POST `/{event}/complete` + GET `/upcoming` + GET `/calendar`
- `/event-categories` — Full CRUD
- `/events/{event}/participants` — Full CRUD + POST `/bulk-invite` + POST `/{participant}/confirm` + POST `/{participant}/decline`

### Tests
- Feature: Event lifecycle, participant management, calendar query, recurring events
- Unit: Date range logic, participant capacity check, recurrence pattern

---

## TASK 17: CRM Module

### Description
Contacts, leads, opportunities, and activity tracking.

### Migration: `contacts`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| first_name | string(100) | not null |
| last_name | string(100) | not null |
| email | string(255) | nullable |
| phone | string(50) | nullable |
| mobile | string(50) | nullable |
| job_title | string(255) | nullable |
| organization | string(255) | nullable |
| customer_id | bigint | FK -> customers.id, nullable |
| supplier_id | bigint | FK -> suppliers.id, nullable |
| address | text | nullable |
| city | string(100) | nullable |
| country | string(100) | nullable |
| source | enum('website','referral','social_media','cold_call','trade_show','advertisement','other') | nullable |
| tags | jsonb | nullable |
| notes | text | nullable |
| is_active | boolean | default true |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `leads`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| contact_id | bigint | FK -> contacts.id, nullable |
| title | string(255) | not null |
| description | text | nullable |
| source | enum('website','referral','social_media','cold_call','trade_show','advertisement','other') | nullable |
| estimated_value | decimal(15,2) | nullable |
| probability | integer | nullable (0-100) |
| assigned_to | bigint | FK -> users.id, nullable |
| expected_close_date | date | nullable |
| status | enum('new','contacted','qualified','unqualified','converted','lost') | default 'new' |
| lost_reason | text | nullable |
| converted_to_opportunity_id | bigint | nullable |
| tags | jsonb | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `opportunities`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| lead_id | bigint | FK -> leads.id, nullable |
| contact_id | bigint | FK -> contacts.id, nullable |
| customer_id | bigint | FK -> customers.id, nullable |
| title | string(255) | not null |
| description | text | nullable |
| value | decimal(15,2) | default 0 |
| probability | integer | default 0 |
| weighted_value | decimal(15,2) | default 0 |
| stage | enum('prospecting','qualification','proposal','negotiation','closed_won','closed_lost') | default 'prospecting' |
| expected_close_date | date | nullable |
| actual_close_date | date | nullable |
| assigned_to | bigint | FK -> users.id, nullable |
| lost_reason | text | nullable |
| won_quote_id | bigint | FK -> quotes.id, nullable |
| tags | jsonb | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `activities`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| type | enum('call','email','meeting','task','note','follow_up') | not null |
| subject | string(255) | not null |
| description | text | nullable |
| related_type | string(255) | nullable (polymorphic: Contact, Lead, Opportunity, Customer) |
| related_id | bigint | nullable |
| assigned_to | bigint | FK -> users.id, nullable |
| due_date | timestamp | nullable |
| completed_at | timestamp | nullable |
| priority | enum('low','medium','high') | default 'medium' |
| status | enum('pending','in_progress','completed','cancelled') | default 'pending' |
| outcome | text | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |

### Services
- `ContactService` — CRUD, search, merge duplicates
- `LeadService` — CRUD, qualify, convert to opportunity, assign, pipeline stats
- `OpportunityService` — CRUD, advance stage, win, lose, pipeline report, forecast
- `ActivityService` — CRUD, complete, overdue list, getByRelated

### Routes
- `/contacts` — Full CRUD + GET `/search` + POST `/{contact}/merge`
- `/leads` — Full CRUD + POST `/{lead}/qualify` + POST `/{lead}/convert` + POST `/{lead}/assign` + GET `/pipeline`
- `/opportunities` — Full CRUD + POST `/{opportunity}/advance` + POST `/{opportunity}/win` + POST `/{opportunity}/lose` + GET `/pipeline` + GET `/forecast`
- `/activities` — Full CRUD + POST `/{activity}/complete` + GET `/overdue` + GET `/upcoming`

### Tests
- Feature: Lead → Opportunity → Won flow, activity tracking, pipeline stats
- Unit: Weighted value calc, stage transitions, forecast logic

---

## TASK 18: Project Management Module

### Description
Projects, tasks, and timesheets for internal project tracking.

### Migration: `projects`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| reference | string(100) | not null |
| name | string(255) | not null |
| description | text | nullable |
| customer_id | bigint | FK -> customers.id, nullable |
| manager_id | bigint | FK -> users.id, nullable |
| start_date | date | nullable |
| end_date | date | nullable |
| budget | decimal(15,2) | nullable |
| actual_cost | decimal(15,2) | default 0 |
| progress | integer | default 0 (0-100) |
| priority | enum('low','medium','high','critical') | default 'medium' |
| status | enum('draft','planned','in_progress','on_hold','completed','cancelled') | default 'draft' |
| tags | jsonb | nullable |
| notes | text | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### Migration: `tasks`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| project_id | bigint | FK -> projects.id, cascade |
| parent_id | bigint | FK self-ref, nullable |
| title | string(255) | not null |
| description | text | nullable |
| assigned_to | bigint | FK -> users.id, nullable |
| start_date | date | nullable |
| due_date | date | nullable |
| completed_at | timestamp | nullable |
| estimated_hours | decimal(8,2) | nullable |
| actual_hours | decimal(8,2) | default 0 |
| priority | enum('low','medium','high','critical') | default 'medium' |
| status | enum('todo','in_progress','review','done','cancelled') | default 'todo' |
| sort_order | integer | default 0 |
| tags | jsonb | nullable |
| created_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration: `timesheets`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| project_id | bigint | FK -> projects.id |
| task_id | bigint | FK -> tasks.id, nullable |
| personnel_id | bigint | FK -> personnels.id |
| date | date | not null |
| hours | decimal(5,2) | not null |
| description | text | nullable |
| is_billable | boolean | default true |
| hourly_rate | decimal(10,2) | nullable |
| status | enum('draft','submitted','approved','rejected') | default 'draft' |
| approved_by | bigint | FK -> users.id, nullable |
| approved_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Services
- `ProjectService` — CRUD, updateProgress, getStats, getDashboard
- `TaskService` — CRUD, assign, changeStatus, getByProject, getMyTasks, calculateProgress
- `TimesheetService` — CRUD, submit, approve, reject, getByProject, getByPersonnel, billingReport

### Routes
- `/projects` — Full CRUD + GET `/{project}/stats` + GET `/dashboard`
- `/projects/{project}/tasks` — Full CRUD + POST `/{task}/assign` + POST `/{task}/status` + GET `/my-tasks`
- `/timesheets` — Full CRUD + POST `/{timesheet}/submit` + POST `/{timesheet}/approve` + POST `/{timesheet}/reject` + GET `/report`

### Tests
- Feature: Project lifecycle, task assignment/status flow, timesheet approval flow
- Unit: Progress calculation, billable hours, cost tracking

---

## TASK 19: Settings & Configuration Module

### Description
Global settings, sequences (auto-numbering), and audit logs.

### Migration: `settings`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id, nullable (null=global) |
| group | string(100) | not null |
| key | string(255) | not null |
| value | text | nullable |
| type | enum('string','integer','boolean','json','date') | default 'string' |
| description | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (company_id, group, key)

### Migration: `sequences`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| model_type | string(255) | not null |
| prefix | string(50) | not null |
| suffix | string(50) | nullable |
| next_number | integer | default 1 |
| padding | integer | default 5 |
| reset_period | enum('never','yearly','monthly') | default 'yearly' |
| last_reset_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (company_id, model_type)

Example sequences:
- Quotes: `DEV-{YYYY}-{00001}`
- Sales Orders: `BC-{YYYY}-{00001}`
- Invoices: `FAC-{YYYY}-{00001}`
- Purchase Orders: `BCA-{YYYY}-{00001}`
- Reception Notes: `BR-{YYYY}-{00001}`

### Migration: `audit_logs`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id, nullable |
| user_id | bigint | FK -> users.id, nullable |
| action | string(50) | not null (created, updated, deleted, login, etc.) |
| auditable_type | string(255) | not null |
| auditable_id | bigint | not null |
| old_values | jsonb | nullable |
| new_values | jsonb | nullable |
| ip_address | string(45) | nullable |
| user_agent | text | nullable |
| url | text | nullable |
| created_at | timestamp | |

**Index**: (auditable_type, auditable_id), (user_id), (company_id, created_at)

### Migration: `notifications`

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| company_id | bigint | FK -> companies.id |
| user_id | bigint | FK -> users.id |
| type | string(255) | not null |
| title | string(255) | not null |
| body | text | nullable |
| data | jsonb | nullable |
| related_type | string(255) | nullable |
| related_id | bigint | nullable |
| read_at | timestamp | nullable |
| created_at | timestamp | |

### Migration: `document_attachments` (polymorphic)

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| company_id | bigint | FK -> companies.id |
| attachable_type | string(255) | not null |
| attachable_id | bigint | not null |
| name | string(255) | not null |
| file_path | string(500) | not null |
| file_size | integer | nullable |
| mime_type | string(100) | nullable |
| uploaded_by | bigint | FK -> users.id |
| created_at | timestamp | |
| updated_at | timestamp | |

### Services
- `SettingService` — get, set, getGroup, getCompanySettings
- `SequenceService` — getNextNumber, reset, format reference string
- `AuditLogService` — log, getByModel, getByUser, search
- `NotificationService` — send, markRead, markAllRead, getUnread
- `AttachmentService` — upload, delete, getByModel

### Routes
- `/settings` — GET `/` + PUT `/` + GET `/groups/{group}`
- `/sequences` — Full CRUD
- `/audit-logs` — GET `/` (filterable) + GET `/{model}/{id}`
- `/notifications` — GET `/` + POST `/{notification}/read` + POST `/read-all` + GET `/unread-count`
- `/attachments` — POST `/upload` + DELETE `/{attachment}` + GET `/{model}/{id}`

### Tests
- Feature: Settings CRUD, sequence generation, audit log creation, notification flow
- Unit: Sequence formatting, reset logic, setting type casting

## TASK 20: Complete Permissions Matrix

### All Modules & Their Permission Slugs

The `PermissionSeeder` must generate ALL of the following permissions. Pattern: `{module}.{action}`

**Actions per module**: `view_any`, `view`, `create`, `update`, `delete`, `restore`, `force_delete`, `export`, `import`, `print`

| Module Slug | Model | Total Permissions |
|---|---|---|
| companies | Company | 10 |
| users | User | 10 |
| roles | Role | 10 |
| permissions | Permission | 10 |
| personnels | Personnel | 10 |
| departments | Department | 10 |
| positions | Position | 10 |
| contracts | Contract | 10 |
| leaves | Leave | 10 + `approve`, `reject` = 12 |
| attendances | Attendance | 10 |
| customers | Customer | 10 |
| quotes | Quote | 10 + `send`, `accept`, `reject`, `convert`, `duplicate` = 15 |
| sales_orders | SalesOrder | 10 + `confirm`, `cancel`, `generate_invoice`, `generate_delivery` = 14 |
| invoices | Invoice | 10 + `send`, `cancel`, `record_payment`, `create_credit_note` = 14 |
| credit_notes | CreditNote | 10 + `confirm`, `apply` = 12 |
| delivery_notes | DeliveryNote | 10 + `ship`, `deliver` = 12 |
| suppliers | Supplier | 10 |
| purchase_requests | PurchaseRequest | 10 + `submit`, `approve`, `reject`, `convert` = 14 |
| purchase_orders | PurchaseOrder | 10 + `send`, `confirm`, `cancel`, `generate_reception`, `generate_invoice` = 15 |
| reception_notes | ReceptionNote | 10 + `confirm`, `cancel` = 12 |
| purchase_invoices | PurchaseInvoice | 10 + `record_payment` = 11 |
| products | Product | 10 |
| product_categories | ProductCategory | 10 |
| warehouses | Warehouse | 10 |
| stock_movements | StockMovement | 10 + `transfer` = 11 |
| stock_inventories | StockInventory | 10 + `start`, `validate` = 12 |
| currencies | Currency | 10 |
| taxes | Tax | 10 |
| payment_terms | PaymentTerm | 10 |
| payment_methods | PaymentMethod | 10 |
| chart_of_accounts | ChartOfAccount | 10 |
| bank_accounts | BankAccount | 10 |
| journal_entries | JournalEntry | 10 + `post`, `cancel` = 12 |
| payments | Payment | 10 + `confirm`, `cancel` = 12 |
| cautions | Caution | 10 + `activate`, `partial_return`, `full_return`, `extend`, `forfeit`, `cancel` = 16 |
| caution_types | CautionType | 10 |
| events | Event | 10 + `confirm`, `cancel`, `complete` = 13 |
| event_categories | EventCategory | 10 |
| event_participants | EventParticipant | 10 + `invite`, `bulk_invite` = 12 |
| contacts | Contact | 10 + `merge` = 11 |
| leads | Lead | 10 + `qualify`, `convert`, `assign` = 13 |
| opportunities | Opportunity | 10 + `advance`, `win`, `lose` = 13 |
| activities | Activity | 10 + `complete` = 11 |
| projects | Project | 10 |
| tasks | Task | 10 + `assign`, `change_status` = 12 |
| timesheets | Timesheet | 10 + `submit`, `approve`, `reject` = 13 |
| settings | Setting | 10 |
| sequences | Sequence | 10 |
| audit_logs | AuditLog | `view_any`, `view` = 2 |
| notifications | Notification | `view_any`, `view`, `delete` = 3 |
| attachments | Attachment | `view_any`, `view`, `create`, `delete` = 4 |

**TOTAL: ~480+ permissions**

### PermissionSeeder Implementation

```php
class PermissionSeeder extends Seeder
{
    private array $modules = [
        'companies' => ['view_any', 'view', 'create', 'update', 'delete', 'restore', 'force_delete', 'export', 'import', 'print'],
        'users' => ['view_any', 'view', 'create', 'update', 'delete', 'restore', 'force_delete', 'export', 'import', 'print'],
        // ... all modules with their specific actions
        'quotes' => ['view_any', 'view', 'create', 'update', 'delete', 'restore', 'force_delete', 'export', 'import', 'print', 'send', 'accept', 'reject', 'convert', 'duplicate'],
        // etc.
    ];

    public function run(): void
    {
        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'slug' => "{$module}.{$action}",
                ], [
                    'module' => $module,
                    'name' => ucfirst(str_replace('_', ' ', $action)) . ' ' . ucfirst(str_replace('_', ' ', $module)),
                    'description' => "Permission to {$action} {$module}",
                ]);
            }
        }
    }
}
```

### Sanctum Permission Check Pattern (3-Table RBAC)

```php
// User.php — Permission check via roles
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'role_user')
        ->withPivot('company_id')
        ->withTimestamps();
}

public function hasPermission(string $permissionSlug): bool
{
    // Get all roles for current company, check if ANY role has the permission
    return $this->roles()
        ->wherePivot('company_id', $this->current_company_id)
        ->whereHas('permissions', fn($q) => $q->where('slug', $permissionSlug))
        ->exists();
}

public function hasRole(string $roleSlug): bool
{
    return $this->roles()
        ->wherePivot('company_id', $this->current_company_id)
        ->where('slug', $roleSlug)
        ->exists();
}

// Middleware: CheckPermission
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (!$user) abort(401);
        if (!$user->current_company_id) abort(403, 'No company selected');

        // Simple check: does any of the user's roles (in current company) have this permission?
        if (!$user->hasPermission($permission)) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}

// Usage in routes
Route::middleware(['auth:sanctum', 'company', 'permission:invoices.create'])
    ->post('/invoices', [InvoiceController::class, 'store']);

// Usage in Blade views
@if(auth()->user()->hasPermission('invoices.create'))
    <a href="{{ route('invoices.create') }}" class="btn btn-primary">Nouvelle Facture</a>
@endif

@if(auth()->user()->hasPermission('invoices.delete'))
    <button class="btn btn-danger">Supprimer</button>
@endif
```

---

## TASK 21: Security Implementation

### Security Rules to Implement

1. **Authentication**
   - Laravel Sanctum token-based auth
   - Token expiration: 24h (configurable via settings)
   - Password hashing: bcrypt with cost 12
   - Password policy: min 8 chars, uppercase, lowercase, number, special char
   - Account lockout: 5 failed attempts → 15 min lock
   - Password history: prevent reuse of last 5 passwords

2. **Authorization**
   - RBAC with company-scoped roles
   - Policy-based authorization on every controller action
   - Middleware-based permission checks
   - Super admin bypass

3. **Input Validation**
   - FormRequest classes for every store/update action
   - XSS sanitization middleware
   - SQL injection prevention (Eloquent parameterized queries)
   - File upload validation (type, size, mime)

4. **Rate Limiting**
   - Login: 5 attempts/minute
   - API: 60 requests/minute per user
   - File upload: 10/minute
   - Export: 5/minute

5. **Data Protection**
   - Soft deletes on all major models
   - Audit trail on all CUD operations
   - Encrypted sensitive fields (bank accounts, SSN)
   - CORS configuration
   - HTTPS enforcement in production

6. **Multi-Tenancy Security**
   - Global scope on all tenant models
   - Middleware validates company access
   - No cross-tenant data leakage
   - Company switch requires membership validation

### Migration: `password_histories`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| user_id | bigint | FK -> users.id |
| password | string(255) | not null |
| created_at | timestamp | |

### Migration: `login_attempts`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| email | string(255) | not null |
| ip_address | string(45) | not null |
| user_agent | text | nullable |
| successful | boolean | default false |
| created_at | timestamp | |

---

## TASK 22: Testing Strategy

### Testing Approach: TDD with Feature + Unit Tests

**Directory Structure:**
```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   ├── RegisterTest.php
│   │   ├── PasswordResetTest.php
│   │   └── TokenTest.php
│   ├── Company/
│   │   ├── CompanyCrudTest.php
│   │   ├── CompanyUserTest.php
│   │   └── CompanySwitchTest.php
│   ├── Personnel/
│   │   ├── PersonnelCrudTest.php
│   │   ├── DepartmentTest.php
│   │   ├── ContractTest.php
│   │   ├── LeaveTest.php
│   │   └── AttendanceTest.php
│   ├── Sales/
│   │   ├── CustomerTest.php
│   │   ├── QuoteTest.php
│   │   ├── QuoteLifecycleTest.php
│   │   ├── SalesOrderTest.php
│   │   ├── InvoiceTest.php
│   │   ├── InvoicePaymentTest.php
│   │   ├── CreditNoteTest.php
│   │   └── DeliveryNoteTest.php
│   ├── Purchasing/
│   │   ├── SupplierTest.php
│   │   ├── PurchaseRequestTest.php
│   │   ├── PurchaseOrderTest.php
│   │   ├── ReceptionNoteTest.php
│   │   └── PurchaseInvoiceTest.php
│   ├── Inventory/
│   │   ├── ProductTest.php
│   │   ├── CategoryTest.php
│   │   ├── WarehouseTest.php
│   │   ├── StockMovementTest.php
│   │   └── StockInventoryTest.php
│   ├── Finance/
│   │   ├── ChartOfAccountTest.php
│   │   ├── JournalEntryTest.php
│   │   ├── PaymentTest.php
│   │   ├── BankAccountTest.php
│   │   └── TaxTest.php
│   ├── Caution/
│   │   ├── CautionCrudTest.php
│   │   └── CautionLifecycleTest.php
│   ├── Event/
│   │   ├── EventTest.php
│   │   └── ParticipantTest.php
│   ├── CRM/
│   │   ├── ContactTest.php
│   │   ├── LeadTest.php
│   │   ├── OpportunityTest.php
│   │   └── ActivityTest.php
│   ├── Project/
│   │   ├── ProjectTest.php
│   │   ├── TaskTest.php
│   │   └── TimesheetTest.php
│   ├── Settings/
│   │   ├── SettingTest.php
│   │   └── SequenceTest.php
│   └── Security/
│       ├── TenantIsolationTest.php
│       ├── PermissionTest.php
│       ├── RateLimitTest.php
│       └── AuditLogTest.php
├── Unit/
│   ├── Services/
│   │   ├── AuthServiceTest.php
│   │   ├── CompanyServiceTest.php
│   │   ├── QuoteServiceTest.php
│   │   ├── InvoiceServiceTest.php
│   │   ├── StockMovementServiceTest.php
│   │   ├── PaymentServiceTest.php
│   │   ├── CautionServiceTest.php
│   │   ├── SequenceServiceTest.php
│   │   └── ... (one per service)
│   ├── Traits/
│   │   ├── BelongsToCompanyTest.php
│   │   ├── HasAuditTrailTest.php
│   │   └── GeneratesReferenceTest.php
│   └── Models/
│       ├── UserTest.php
│       ├── InvoiceCalculationTest.php
│       └── StockLevelTest.php
└── Helpers/
    ├── TestCase.php (base with company/user setup)
    └── Factories/ (one factory per model)
```

### Base TestCase Helper

```php
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected string $token;

    protected function setUpCompanyAndUser(string $roleName = 'admin'): void
    {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);

        $role = Role::factory()->create(['company_id' => $this->company->id, 'name' => $roleName]);
        $this->user->companies()->attach($this->company->id, ['role_id' => $role->id]);

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'X-Company-Id' => (string) $this->company->id,
            'Accept' => 'application/json',
        ];
    }

    protected function assertTenantIsolation(string $model, array $data): void
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['current_company_id' => $otherCompany->id]);
        $record = $model::factory()->create(array_merge($data, ['company_id' => $otherCompany->id]));

        $this->getJson(route("{$model}.show", $record), $this->authHeaders())
            ->assertStatus(404);
    }
}
```

### Key Test Patterns

**Every Feature test must verify:**
1. Successful CRUD operations
2. Validation errors (missing required fields, invalid data)
3. Authorization (forbidden without permission)
4. Tenant isolation (cannot access other company's data)
5. Status transitions (invalid transitions rejected)

**Example Feature Test:**

```php
class QuoteTest extends TestCase
{
    public function test_can_create_quote_with_lines(): void { /* ... */ }
    public function test_cannot_create_quote_without_permission(): void { /* ... */ }
    public function test_cannot_access_other_company_quote(): void { /* ... */ }
    public function test_can_send_draft_quote(): void { /* ... */ }
    public function test_cannot_send_already_sent_quote(): void { /* ... */ }
    public function test_can_convert_accepted_quote_to_order(): void { /* ... */ }
    public function test_totals_calculated_correctly(): void { /* ... */ }
    public function test_reference_auto_generated(): void { /* ... */ }
}
```

---

## TASK 23: Frontend Layout with Tailwind CSS

### Description
Blade-based layout with Tailwind CSS. Sidebar navigation, top bar with company switcher, responsive.

### Layout Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php          — Main authenticated layout
│   ├── guest.blade.php        — Login/register layout
│   └── partials/
│       ├── sidebar.blade.php  — Left sidebar with module navigation
│       ├── topbar.blade.php   — Top bar: company switcher, user menu, notifications
│       └── footer.blade.php
├── auth/
│   ├── login.blade.php
│   ├── register.blade.php
│   ├── forgot-password.blade.php
│   └── reset-password.blade.php
├── dashboard/
│   └── index.blade.php        — Dashboard with widgets per module
├── companies/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
├── (... one folder per module with index/create/edit/show)
└── components/
    ├── alert.blade.php
    ├── badge.blade.php
    ├── button.blade.php
    ├── card.blade.php
    ├── data-table.blade.php
    ├── dropdown.blade.php
    ├── form-input.blade.php
    ├── form-select.blade.php
    ├── modal.blade.php
    ├── pagination.blade.php
    ├── status-badge.blade.php
    ├── stats-card.blade.php
    └── breadcrumb.blade.php
```

### Sidebar Navigation Groups
1. **Dashboard** — /dashboard
2. **HR** — Personnel, Departments, Positions, Contracts, Leaves, Attendance
3. **Sales** — Customers, Quotes, Sales Orders, Invoices, Credit Notes, Delivery Notes
4. **Purchasing** — Suppliers, Purchase Requests, Purchase Orders, Reception Notes, Purchase Invoices
5. **Inventory** — Products, Categories, Warehouses, Stock Movements, Inventories
6. **Finance** — Chart of Accounts, Journal Entries, Payments, Bank Accounts, Taxes
7. **Caution** — Caution Types, Cautions
8. **Events** — Categories, Events
9. **CRM** — Contacts, Leads, Opportunities, Activities
10. **Projects** — Projects, Tasks, Timesheets
11. **Settings** — Company Settings, Users, Roles, Sequences

### Web Routes Structure

All web routes require `auth`, `verified`, `company` middleware.

```php
Route::middleware(['auth', 'verified', 'company'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Company switcher
    Route::post('/switch-company/{company}', [CompanyController::class, 'switch'])->name('company.switch');

    // Each module has resource routes:
    Route::resource('companies', CompanyController::class);
    Route::resource('personnels', PersonnelController::class);
    Route::resource('departments', DepartmentController::class);
    // ... etc for all modules

    // Additional action routes
    Route::post('quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
    // ... etc
});
```

---

## TASK 24: Database Factories & Seeders

### Description
Create factories for every model and seeders for demo data.

### Factories (one per model)
- `CompanyFactory`
- `UserFactory`
- `RoleFactory`
- `PermissionFactory`
- `PersonnelFactory`
- `DepartmentFactory`
- `PositionFactory`
- `ContractFactory`
- `LeaveFactory`
- `AttendanceFactory`
- `CustomerFactory`
- `SupplierFactory`
- `QuoteFactory` + `QuoteLineFactory`
- `SalesOrderFactory` + `SalesOrderLineFactory`
- `InvoiceFactory` + `InvoiceLineFactory`
- `CreditNoteFactory` + `CreditNoteLineFactory`
- `DeliveryNoteFactory` + `DeliveryNoteLineFactory`
- `PurchaseRequestFactory` + `PurchaseRequestLineFactory`
- `PurchaseOrderFactory` + `PurchaseOrderLineFactory`
- `ReceptionNoteFactory` + `ReceptionNoteLineFactory`
- `PurchaseInvoiceFactory` + `PurchaseInvoiceLineFactory`
- `ProductFactory`
- `ProductCategoryFactory`
- `WarehouseFactory`
- `StockMovementFactory`
- `StockInventoryFactory`
- `CurrencyFactory`
- `TaxFactory`
- `PaymentTermFactory`
- `PaymentMethodFactory`
- `ChartOfAccountFactory`
- `BankAccountFactory`
- `JournalEntryFactory`
- `PaymentFactory`
- `CautionFactory`
- `CautionTypeFactory`
- `EventFactory`
- `EventCategoryFactory`
- `ContactFactory`
- `LeadFactory`
- `OpportunityFactory`
- `ActivityFactory`
- `ProjectFactory`
- `TaskFactory`
- `TimesheetFactory`
- `SettingFactory`
- `SequenceFactory`

### Seeders
1. `PermissionSeeder` — all 480+ permissions
2. `RoleSeeder` — 5 default roles with permissions
3. `CurrencySeeder` — MAD, EUR, USD, GBP
4. `TaxSeeder` — TVA 20%, TVA 14%, TVA 10%, TVA 7%, Exempt
5. `DemoSeeder` — creates 2 companies with sample data for testing

---

## Reference Number Formats

| Document | Prefix | Format Example |
|---|---|---|
| Personnel Matricule | EMP | EMP-2026-00001 |
| Quote | DEV | DEV-2026-00001 |
| Sales Order | BC | BC-2026-00001 |
| Invoice | FAC | FAC-2026-00001 |
| Credit Note | AV | AV-2026-00001 |
| Delivery Note | BL | BL-2026-00001 |
| Purchase Request | DA | DA-2026-00001 |
| Purchase Order | BCA | BCA-2026-00001 |
| Reception Note | BR | BR-2026-00001 |
| Purchase Invoice | FAF | FAF-2026-00001 |
| Payment | PAY | PAY-2026-00001 |
| Caution | CAU | CAU-2026-00001 |
| Stock Movement | MV | MV-2026-00001 |
| Stock Inventory | INV | INV-2026-00001 |
| Journal Entry | JE | JE-2026-00001 |
| Project | PRJ | PRJ-2026-00001 |
| Lead | LD | LD-2026-00001 |
| Opportunity | OPP | OPP-2026-00001 |
