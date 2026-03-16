# ERP Multi-Société — Task List

Each task is self-contained and can be executed independently by KIRO. Tasks are ordered by dependency.

---

## TASK 1: Project Setup & Base Configuration
- [x] Configure `.env` for PostgreSQL connection
- [x] Install `laravel/sanctum` via composer (`composer require laravel/sanctum`)
- [x] Install and configure Tailwind CSS (`npm install -D tailwindcss @tailwindcss/vite`)
- [x] Configure `tailwind.config.js` and update `vite.config.js`
- [x] Create domain subfolders in standard MVC structure:
  - `app/Models/{Auth,Company,Personnel,Sales,Purchasing,Inventory,Finance,Caution,Event,CRM,Project,Settings}`
  - `app/Http/Controllers/{Auth,Company,Personnel,Sales,Purchasing,Inventory,Finance,Caution,Event,CRM,Project,Settings}`
  - `app/Http/Requests/{Auth,Company,Personnel,Sales,Purchasing,Inventory,Finance,Caution,Event,CRM,Project,Settings}`
  - `app/Http/Resources/{Auth,Company,Personnel,Sales,Purchasing,Inventory,Finance,Caution,Event,CRM,Project,Settings}`
  - `app/Services/{Auth,Company,Personnel,Sales,Purchasing,Inventory,Finance,Caution,Event,CRM,Project,Settings}`
  - `app/Policies/{Auth,Company,Personnel,Sales,Purchasing,Inventory,Finance,Caution,Event,CRM,Project,Settings}`
- [x] Create `app/Traits/BelongsToCompany.php` — global scope + auto company_id on create
- [x] Create `app/Traits/HasAuditTrail.php` — boot method to log create/update/delete to audit_logs
- [x] Create `app/Traits/GeneratesReference.php` — uses SequenceService to auto-generate reference on create
- [x] Create `app/Traits/HasStatus.php` — status transition validation helper
- [x] Configure `config/sanctum.php` — token expiration 24h
- [x] Configure `config/auth.php` — sanctum guard
- [x] Update `tests/TestCase.php` — add helpers: `setUpCompanyAndUser()`, `authHeaders()`, `assertTenantIsolation()`
- [x] Verify: `php artisan migrate` succeeds, `npm run build` succeeds, test suite baseline passes

## TASK 2: Company Module
- [x] Create migration `create_companies_table` (see design.md for schema)
- [x] Create migration `create_company_user_table` (pivot: user_id, company_id, is_default, joined_at — NO role_id, roles are separate via role_user)
- [x] Create `app/Models/Company/Company.php` — SoftDeletes, relationships (users M2M), scope active()
- [x] Create `app/Services/Company/CompanyService.php` — create, update, delete, addUser, removeUser, switchCompany
- [x] Create `app/Http/Controllers/Company/CompanyController.php` — CRUD + addUser + removeUser + switch
- [x] Create `app/Http/Requests/Company/StoreCompanyRequest.php`, `UpdateCompanyRequest.php`
- [x] Create `app/Http/Resources/Company/CompanyResource.php`
- [x] Create `app/Policies/Company/CompanyPolicy.php`
- [x] Create `app/Http/Middleware/SetCurrentCompany.php`
- [x] Register routes: `/companies` CRUD + `/companies/{company}/users` + `/companies/{company}/switch`
- [x] Create `database/factories/CompanyFactory.php`
- [x] Create `tests/Feature/Company/CompanyCrudTest.php` — CRUD, add/remove user, switch, tenant isolation
- [x] Create `tests/Unit/Services/CompanyServiceTest.php`

## TASK 3: Users & Authentication Module
- [x] Modify migration `create_users_table` — add: matricule, first_name, last_name, phone, avatar_path, current_company_id, is_active (NO is_superadmin — super admin is just a role), last_login_at, last_login_ip, password_changed_at, deleted_at
- [x] Create migration `create_password_histories_table`
- [x] Create migration `create_login_attempts_table`
- [x] Update `app/Models/Auth/User.php` — HasApiTokens, SoftDeletes, relations: companies() BelongsToMany, currentCompany() BelongsTo, roles() BelongsToMany (with company_id pivot), personnel() HasOne. Methods: hasPermission(string $slug): bool (checks all user roles in current company for that permission), hasRole(string $roleSlug): bool, getRolesForCompany(?int $companyId): Collection. Matricule auto-generation on boot.
- [x] Create `app/Services/Auth/AuthService.php` — register, login (with rate limit check), logout, refreshToken, changePassword (with history check), forgotPassword, resetPassword
- [x] Create `app/Http/Controllers/Auth/AuthController.php` — all auth endpoints
- [x] Create `app/Http/Requests/Auth/LoginRequest.php`, `RegisterRequest.php`, `ChangePasswordRequest.php`
- [x] Create `app/Http/Resources/Auth/UserResource.php`
- [x] Create `app/Http/Middleware/CheckPermission.php`
- [x] Register auth routes: `/auth/register`, `/auth/login`, `/auth/logout`, `/auth/me`, `/auth/refresh-token`, `/auth/change-password`, `/auth/forgot-password`, `/auth/reset-password`
- [x] Configure rate limiting in AppServiceProvider: login 5/min, api 60/min
- [x] Create `database/factories/UserFactory.php`
- [x] Create `tests/Feature/Auth/LoginTest.php` — login, invalid credentials, rate limiting, lockout
- [x] Create `tests/Feature/Auth/RegisterTest.php` — register, validation, matricule generation
- [x] Create `tests/Feature/Auth/PasswordResetTest.php`
- [x] Create `tests/Feature/Auth/TokenTest.php` — token creation, refresh, expiry
- [x] Create `tests/Unit/Services/AuthServiceTest.php`

## TASK 4: Roles & Permissions Module (3-Table RBAC: users ←→ roles ←→ permissions)
- [ ] Create migration `create_roles_table` — id, company_id (FK nullable, null=global), name, slug, description, is_system, timestamps. Unique: (company_id, slug)
- [ ] Create migration `create_permissions_table` — id, module, name, slug (unique), description, timestamps
- [ ] Create migration `create_permission_role_table` (pivot) — role_id FK, permission_id FK. Primary key: (role_id, permission_id)
- [ ] Create migration `create_role_user_table` (pivot) — id, role_id FK, user_id FK, company_id FK, timestamps. Unique: (role_id, user_id, company_id). This allows a user to have different roles per company.
- [ ] **NO `user_permissions` table** — permissions are ONLY assigned to roles, never directly to users
- [ ] Create `app/Models/Auth/Role.php` — relationships: permissions() BelongsToMany, users() BelongsToMany (with company_id pivot). Scope: forCompany($companyId)
- [ ] Create `app/Models/Auth/Permission.php` — relationships: roles() BelongsToMany
- [ ] Create `app/Services/Auth/RoleService.php` — create, update, delete, assignPermissions(Role, array $permissionIds), revokePermissions(Role, array $permissionIds), syncPermissions(Role, array $permissionIds), assignToUser(Role, User, Company), removeFromUser(Role, User, Company)
- [ ] Create `app/Services/Auth/PermissionService.php` — seedAllPermissions(), getByModule(string $module), getAllGroupedByModule(): array. NO userCan — that logic lives in User::hasPermission()
- [ ] Create `app/Http/Controllers/Auth/RoleController.php`, `PermissionController.php`
- [ ] Create `app/Http/Requests/Auth/StoreRoleRequest.php`, `UpdateRoleRequest.php`
- [ ] Create `app/Http/Resources/Auth/RoleResource.php`, `PermissionResource.php`
- [ ] Create `app/Policies/Auth/RolePolicy.php`
- [ ] Create `database/seeders/PermissionSeeder.php` — generate all ~480 permissions
- [ ] Create `database/seeders/RoleSeeder.php` — 5 default roles with permission assignments
- [ ] Register routes: `/roles` CRUD + `/roles/{role}/permissions`, `/permissions`
- [ ] Create `tests/Feature/Auth/RoleTest.php` — CRUD, assign/revoke permissions
- [ ] Create `tests/Feature/Security/PermissionTest.php` — middleware check: user with role that has permission → 200, user with role without permission → 403, user with no roles → 403, user with multiple roles (permissions merged from all roles)
- [ ] Create `tests/Unit/Services/PermissionServiceTest.php` — seeder completeness, getByModule, getAllGroupedByModule
- [ ] Create `tests/Unit/Models/UserPermissionTest.php` — hasPermission() with single role, multiple roles, role in different company (should not leak permissions across companies)

## TASK 5: Personnel Module
- [ ] Create migration `create_departments_table`
- [ ] Create migration `create_positions_table`
- [ ] Create migration `create_personnels_table`
- [ ] Create migration `create_contracts_table`
- [ ] Create migration `create_leaves_table`
- [ ] Create migration `create_attendances_table`
- [ ] Create models: `Personnel`, `Department`, `Position`, `Contract`, `Leave`, `Attendance` — all with BelongsToCompany
- [ ] Create services: `PersonnelService`, `DepartmentService`, `PositionService`, `ContractService`, `LeaveService`, `AttendanceService`
- [ ] Create controllers: `PersonnelController`, `DepartmentController`, `PositionController`, `ContractController`, `LeaveController`, `AttendanceController`
- [ ] Create FormRequests for each controller's store/update
- [ ] Create Resources for each model
- [ ] Create Policies for each model
- [ ] Create factories for each model
- [ ] Register all routes (see requirements for full route list)
- [ ] Create Feature tests: PersonnelCrudTest, DepartmentTest, ContractTest, LeaveTest (with approval flow), AttendanceTest
- [ ] Create Unit tests: PersonnelServiceTest (matricule gen, linking), LeaveServiceTest (balance calc), AttendanceServiceTest (hours calc)

## TASK 6: Sales — Customers
- [ ] Create migration `create_customers_table`
- [ ] Create `app/Models/Sales/Customer.php` — BelongsToCompany, SoftDeletes
- [ ] Create `app/Services/Sales/CustomerService.php` — CRUD, search, balance update, credit check
- [ ] Create `app/Http/Controllers/Sales/CustomerController.php`
- [ ] Create FormRequests, Resources, Policy, Factory
- [ ] Register routes: `/customers` CRUD + search
- [ ] Create tests: CustomerTest (CRUD, search, tenant isolation)

## TASK 7: Sales — Quotes (Devis)
- [ ] Create migration `create_quotes_table`
- [ ] Create migration `create_quote_lines_table`
- [ ] Create `Quote.php`, `QuoteLine.php` models
- [ ] Create `QuoteService.php` — CRUD with lines, send, accept, reject, duplicate, convertToOrder, calculateTotals, generatePdf
- [ ] Create `QuoteController.php` — all actions
- [ ] Create FormRequests: StoreQuoteRequest, UpdateQuoteRequest
- [ ] Create QuoteResource, QuoteLineResource
- [ ] Create QuotePolicy
- [ ] Create factories: QuoteFactory, QuoteLineFactory
- [ ] Register routes: `/quotes` CRUD + send + accept + reject + duplicate + convert-to-order + pdf
- [ ] Create tests: QuoteTest (full lifecycle), QuoteCalculationTest (unit)

## TASK 8: Sales — Sales Orders (BC)
- [ ] Create migration `create_sales_orders_table`
- [ ] Create migration `create_sales_order_lines_table` (with delivered_quantity, invoiced_quantity)
- [ ] Create `SalesOrder.php`, `SalesOrderLine.php` models
- [ ] Create `SalesOrderService.php` — CRUD with lines, confirm, cancel, generateInvoice, generateDeliveryNote
- [ ] Create controller, FormRequests, Resources, Policy, Factories
- [ ] Register routes: `/sales-orders` CRUD + confirm + cancel + generate-invoice + generate-delivery-note + pdf
- [ ] Create tests: SalesOrderTest (lifecycle, generation)

## TASK 9: Sales — Invoices (Factures)
- [ ] Create migration `create_invoices_table` (with amount_paid, amount_due)
- [ ] Create migration `create_invoice_lines_table`
- [ ] Create `Invoice.php`, `InvoiceLine.php` models
- [ ] Create `InvoiceService.php` — CRUD with lines, send, cancel, recordPayment, createCreditNote, calculateAmountDue, checkOverdue, generatePdf
- [ ] Create controller, FormRequests, Resources, Policy, Factories
- [ ] Register routes: `/invoices` CRUD + send + cancel + record-payment + credit-note + pdf + overdue
- [ ] Create tests: InvoiceTest (lifecycle), InvoicePaymentTest (partial payments, overdue detection)

## TASK 10: Sales — Credit Notes & Delivery Notes
- [ ] Create migration `create_credit_notes_table` + `create_credit_note_lines_table`
- [ ] Create migration `create_delivery_notes_table` + `create_delivery_note_lines_table`
- [ ] Create models: CreditNote, CreditNoteLine, DeliveryNote, DeliveryNoteLine
- [ ] Create services: CreditNoteService (confirm, apply to invoice balance), DeliveryNoteService (ship, deliver, return, update order delivered qty)
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/credit-notes` CRUD + confirm + apply; `/delivery-notes` CRUD + ship + deliver
- [ ] Create tests: CreditNoteTest, DeliveryNoteTest

## TASK 11: Purchasing — Suppliers
- [ ] Create migration `create_suppliers_table`
- [ ] Create `Supplier.php` model — BelongsToCompany, SoftDeletes
- [ ] Create `SupplierService.php` — CRUD, search, balance management
- [ ] Create controller, FormRequests, Resources, Policy, Factory
- [ ] Register routes: `/suppliers` CRUD + search
- [ ] Create tests: SupplierTest

## TASK 12: Purchasing — Purchase Requests
- [ ] Create migration `create_purchase_requests_table` + `create_purchase_request_lines_table`
- [ ] Create models: PurchaseRequest, PurchaseRequestLine
- [ ] Create `PurchaseRequestService.php` — CRUD, submit, approve, reject, convertToOrder
- [ ] Create controller, FormRequests, Resources, Policy, Factories
- [ ] Register routes: `/purchase-requests` CRUD + submit + approve + reject + convert-to-order
- [ ] Create tests: PurchaseRequestTest (lifecycle with approval)

## TASK 13: Purchasing — Purchase Orders
- [ ] Create migration `create_purchase_orders_table` + `create_purchase_order_lines_table` (with received_quantity, invoiced_quantity)
- [ ] Create models: PurchaseOrder, PurchaseOrderLine
- [ ] Create `PurchaseOrderService.php` — CRUD with lines, send, confirm, cancel, generateReceptionNote, generatePurchaseInvoice
- [ ] Create controller, FormRequests, Resources, Policy, Factories
- [ ] Register routes: `/purchase-orders` CRUD + send + confirm + cancel + generate-reception + generate-invoice + pdf
- [ ] Create tests: PurchaseOrderTest

## TASK 14: Purchasing — Reception Notes
- [ ] Create migration `create_reception_notes_table` + `create_reception_note_lines_table` (ordered_quantity, received_quantity, rejected_quantity)
- [ ] Create models: ReceptionNote, ReceptionNoteLine
- [ ] Create `ReceptionNoteService.php` — CRUD, confirm (triggers stock movement via StockMovementService), cancel
- [ ] Create controller, FormRequests, Resources, Policy, Factories
- [ ] Register routes: `/reception-notes` CRUD + confirm + cancel
- [ ] Create tests: ReceptionNoteTest (confirmation stock impact)

## TASK 15: Purchasing — Purchase Invoices
- [ ] Create migration `create_purchase_invoices_table` + `create_purchase_invoice_lines_table`
- [ ] Create models: PurchaseInvoice, PurchaseInvoiceLine
- [ ] Create `PurchaseInvoiceService.php` — CRUD, recordPayment, markPaid
- [ ] Create controller, FormRequests, Resources, Policy, Factories
- [ ] Register routes: `/purchase-invoices` CRUD + record-payment + pdf
- [ ] Create tests: PurchaseInvoiceTest

## TASK 16: Inventory — Products & Categories
- [ ] Create migration `create_product_categories_table` (hierarchical)
- [ ] Create migration `create_products_table`
- [ ] Create models: Product, ProductCategory — BelongsToCompany
- [ ] Create services: ProductService (CRUD, search, stock level check, low stock alert), ProductCategoryService (CRUD with hierarchy)
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/products` CRUD + low-stock + stock-levels; `/product-categories` CRUD + tree
- [ ] Create tests: ProductTest, CategoryTest

## TASK 17: Inventory — Warehouses & Stock
- [ ] Create migration `create_warehouses_table`
- [ ] Create migration `create_stock_levels_table` (unique: company_id, product_id, warehouse_id)
- [ ] Create migration `create_stock_movements_table` (polymorphic source)
- [ ] Create migration `create_stock_inventories_table` + `create_stock_inventory_lines_table`
- [ ] Create models: Warehouse, StockLevel, StockMovement, StockInventory, StockInventoryLine
- [ ] Create services: WarehouseService, StockMovementService (create movement + update levels + transfer), StockInventoryService (CRUD, validate → adjustment movements)
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/warehouses` CRUD; `/stock-movements` CRUD + transfer + report; `/stock-inventories` CRUD + start + validate
- [ ] Create tests: WarehouseTest, StockMovementTest (in/out/transfer), StockInventoryTest (count → validate → adjustment)

## TASK 18: Finance — Base Entities
- [ ] Create migration `create_currencies_table` (seeded: MAD, EUR, USD, GBP)
- [ ] Create migration `create_taxes_table` (seeded: TVA 20%, 14%, 10%, 7%, Exempt)
- [ ] Create migration `create_payment_terms_table`
- [ ] Create migration `create_payment_methods_table`
- [ ] Create models, services, controllers, requests, resources, policies, factories for each
- [ ] Create `database/seeders/CurrencySeeder.php`, `TaxSeeder.php`
- [ ] Register routes: `/currencies`, `/taxes`, `/payment-terms`, `/payment-methods` — all CRUD
- [ ] Create tests for each entity

## TASK 19: Finance — Chart of Accounts & Bank Accounts
- [ ] Create migration `create_chart_of_accounts_table` (hierarchical, types: asset/liability/equity/revenue/expense)
- [ ] Create migration `create_bank_accounts_table`
- [ ] Create models: ChartOfAccount, BankAccount
- [ ] Create services: ChartOfAccountService (CRUD with hierarchy, balance calculation), BankAccountService (CRUD, balance update)
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/chart-of-accounts` CRUD + tree + balance; `/bank-accounts` CRUD
- [ ] Create tests: ChartOfAccountTest, BankAccountTest

## TASK 20: Finance — Journal Entries & Payments
- [ ] Create migration `create_journal_entries_table` + `create_journal_entry_lines_table` (debit/credit)
- [ ] Create migration `create_payments_table` (polymorphic payable, incoming/outgoing)
- [ ] Create models: JournalEntry, JournalEntryLine, Payment
- [ ] Create `JournalEntryService.php` — CRUD, post (validate debit=credit), cancel
- [ ] Create `PaymentService.php` — CRUD, confirm (creates journal entry + updates invoice balance + bank balance), cancel
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/journal-entries` CRUD + post + cancel; `/payments` CRUD + confirm + cancel
- [ ] Create tests: JournalEntryTest (debit=credit validation), PaymentTest (full flow with journal + balance updates)

## TASK 21: Caution Module
- [ ] Create migration `create_caution_types_table`
- [ ] Create migration `create_cautions_table` (direction: given/received, polymorphic related, status lifecycle)
- [ ] Create migration `create_caution_histories_table`
- [ ] Create models: CautionType, Caution, CautionHistory — BelongsToCompany
- [ ] Create `CautionTypeService.php` — CRUD
- [ ] Create `CautionService.php` — CRUD, activate, partialReturn, fullReturn, extend, forfeit, cancel, getExpiring, getByPartner, getDashboardStats
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/caution-types` CRUD; `/cautions` CRUD + activate + partial-return + full-return + extend + forfeit + cancel + expiring + stats
- [ ] Create tests: CautionCrudTest, CautionLifecycleTest (full lifecycle with history)

## TASK 22: Events Module
- [ ] Create migration `create_event_categories_table`
- [ ] Create migration `create_events_table` (recurring support via jsonb)
- [ ] Create migration `create_event_participants_table`
- [ ] Create models: EventCategory, Event, EventParticipant — BelongsToCompany
- [ ] Create services: EventService (CRUD, confirm, cancel, complete, postpone, getUpcoming, getByDateRange), EventCategoryService, EventParticipantService (invite, confirm, decline, bulkInvite)
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/event-categories` CRUD; `/events` CRUD + confirm + cancel + complete + upcoming + calendar; `/events/{event}/participants` CRUD + bulk-invite + confirm + decline
- [ ] Create tests: EventTest (lifecycle), ParticipantTest

## TASK 23: CRM Module
- [ ] Create migration `create_contacts_table`
- [ ] Create migration `create_leads_table`
- [ ] Create migration `create_opportunities_table`
- [ ] Create migration `create_activities_table` (polymorphic)
- [ ] Create models: Contact, Lead, Opportunity, Activity — BelongsToCompany
- [ ] Create services: ContactService (CRUD, search, merge), LeadService (CRUD, qualify, convert, pipeline stats), OpportunityService (CRUD, advance stage, win, lose, forecast), ActivityService (CRUD, complete, overdue)
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/contacts` CRUD + search + merge; `/leads` CRUD + qualify + convert + assign + pipeline; `/opportunities` CRUD + advance + win + lose + pipeline + forecast; `/activities` CRUD + complete + overdue + upcoming
- [ ] Create tests: ContactTest, LeadTest (conversion flow), OpportunityTest (pipeline), ActivityTest

## TASK 24: Project Management Module
- [ ] Create migration `create_projects_table`
- [ ] Create migration `create_tasks_table` (hierarchical via parent_id)
- [ ] Create migration `create_timesheets_table`
- [ ] Create models: Project, Task, Timesheet — BelongsToCompany
- [ ] Create services: ProjectService (CRUD, updateProgress, getStats), TaskService (CRUD, assign, changeStatus, calculateProgress), TimesheetService (CRUD, submit, approve, reject, billingReport)
- [ ] Create controllers, FormRequests, Resources, Policies, Factories
- [ ] Register routes: `/projects` CRUD + stats + dashboard; `/projects/{project}/tasks` CRUD + assign + status + my-tasks; `/timesheets` CRUD + submit + approve + reject + report
- [ ] Create tests: ProjectTest, TaskTest (status flow), TimesheetTest (approval flow)

## TASK 25: Settings & Configuration Module
- [ ] Create migration `create_settings_table` (company-scoped key-value, grouped)
- [ ] Create migration `create_sequences_table` (auto-numbering per model per company)
- [ ] Create migration `create_audit_logs_table`
- [ ] Create migration `create_notifications_table`
- [ ] Create migration `create_document_attachments_table` (polymorphic)
- [ ] Create models: Setting, Sequence, AuditLog, Notification, Attachment
- [ ] Create services: SettingService (get, set, getGroup), SequenceService (getNextNumber, reset, format), AuditLogService (log, search), NotificationService (send, markRead, getUnread), AttachmentService (upload, delete)
- [ ] Create controllers, FormRequests, Resources
- [ ] Register routes: `/settings` get+set; `/sequences` CRUD; `/audit-logs` index+show; `/notifications` index+read+read-all+unread-count; `/attachments` upload+delete
- [ ] Create tests: SettingTest, SequenceTest (numbering, reset), AuditLogTest, NotificationTest

## TASK 26: Frontend — Layout & Components
- [ ] Create `resources/views/layouts/app.blade.php` — main layout with sidebar + topbar + content area
- [ ] Create `resources/views/layouts/guest.blade.php` — centered card layout for auth
- [ ] Create `resources/views/layouts/partials/sidebar.blade.php` — collapsible sidebar with module groups
- [ ] Create `resources/views/layouts/partials/topbar.blade.php` — company switcher dropdown, user menu, notification bell
- [ ] Create Blade components: x-button, x-card, x-data-table, x-form-input, x-form-select, x-modal, x-alert, x-badge, x-status-badge, x-stats-card, x-breadcrumb, x-dropdown, x-pagination
- [ ] Style all components with Tailwind CSS utility classes
- [ ] Create `resources/views/dashboard/index.blade.php` — stats cards for each module

## TASK 27: Frontend — Auth Pages
- [ ] Create `resources/views/auth/login.blade.php`
- [ ] Create `resources/views/auth/register.blade.php`
- [ ] Create `resources/views/auth/forgot-password.blade.php`
- [ ] Create `resources/views/auth/reset-password.blade.php`
- [ ] Style with Tailwind CSS, use guest layout

## TASK 28: Frontend — Module CRUD Views
- [ ] For each module, create: `index.blade.php` (data table with filters/search), `create.blade.php` (form), `edit.blade.php` (form), `show.blade.php` (detail view)
- [ ] Modules: companies, personnels, departments, positions, contracts, leaves, attendances, customers, quotes, sales-orders, invoices, credit-notes, delivery-notes, suppliers, purchase-requests, purchase-orders, reception-notes, purchase-invoices, products, product-categories, warehouses, stock-movements, stock-inventories, currencies, taxes, payment-terms, payment-methods, chart-of-accounts, bank-accounts, journal-entries, payments, caution-types, cautions, event-categories, events, contacts, leads, opportunities, activities, projects, tasks, timesheets, settings, sequences, roles, users
- [ ] Use reusable components throughout
- [ ] Register web routes for all modules

## TASK 29: Database Seeders & Factories
- [ ] Ensure all model factories are created (see design.md for full list)
- [ ] Create `database/seeders/PermissionSeeder.php` — all ~480 permissions
- [ ] Create `database/seeders/RoleSeeder.php` — 5 default roles
- [ ] Create `database/seeders/CurrencySeeder.php` — MAD, EUR, USD, GBP
- [ ] Create `database/seeders/TaxSeeder.php` — TVA 20%, 14%, 10%, 7%, Exempt
- [ ] Create `database/seeders/DemoSeeder.php` — 2 companies, users, sample data across all modules
- [ ] Create `database/seeders/DatabaseSeeder.php` — orchestrate all seeders
- [ ] Verify: `php artisan db:seed` runs without errors

## TASK 30: Security Hardening
- [ ] Implement XSS sanitization middleware
- [ ] Configure CORS in `config/cors.php`
- [ ] Add HTTPS enforcement middleware for production
- [ ] Implement file upload validation (type whitelist, max size, mime check) in a shared trait or rule
- [ ] Encrypt sensitive fields (bank_account, social_security_number) using Laravel's `encrypted` cast
- [ ] Verify all routes have proper middleware (auth, company, permission)
- [ ] Create `tests/Feature/Security/TenantIsolationTest.php` — verify no cross-company data access across all major models
- [ ] Create `tests/Feature/Security/RateLimitTest.php` — verify rate limits on login, API, uploads
- [ ] Create `tests/Feature/Security/AuditLogTest.php` — verify all CUD operations are logged

## TASK 31: Integration Testing & Final Verification
- [ ] Create integration test: Full sales cycle (Customer → Quote → Accept → Sales Order → Delivery Note → Invoice → Payment)
- [ ] Create integration test: Full purchase cycle (Purchase Request → Approve → PO → Reception Note → Purchase Invoice → Payment)
- [ ] Create integration test: Stock movement flow (Reception → stock in, Delivery → stock out, Transfer, Inventory count → adjustment)
- [ ] Create integration test: Caution lifecycle (Create → Activate → Partial Return → Full Return)
- [ ] Create integration test: Multi-company isolation (create data in company A, verify invisible from company B)
- [ ] Run full test suite: `php artisan test` — all tests pass
- [ ] Run `php artisan migrate:fresh --seed` — verify clean install works
- [ ] Verify `npm run build` produces valid CSS/JS assets
