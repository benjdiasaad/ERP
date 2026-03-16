# ERP Multi-Société — Requirements

## Project Overview

Build a comprehensive multi-company ERP system using Laravel 12, PostgreSQL, Tailwind CSS, and Laravel Sanctum. The system must support multiple companies (sociétés) where each user can belong to N companies, each company has fully isolated data, and every user has a role with granular permissions.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL
- **Authentication**: Laravel Sanctum (token-based)
- **Frontend**: Laravel Blade + Tailwind CSS
- **Testing**: PHPUnit (TDD — Feature + Unit tests)
- **Multi-tenancy**: Single database with `company_id` column on all tenant tables

## Architecture Requirements

- **Domain-Driven Folder Structure**: Each module lives under `app/Domain/{Module}/` with its own Models, Services, Controllers, Requests, Resources, Policies, and Tests.
- **Service Layer Pattern**: All business logic must be in Service classes. Controllers only handle HTTP request/response — no logic in controllers.
- **Traits**: `BelongsToCompany` (auto-scopes queries by company), `HasAuditTrail`, `GeneratesReference`, `HasStatus`.
- **Policies**: Every model must have a Policy class for authorization.
- **FormRequests**: Every store/update action must have a FormRequest for validation.
- **Soft Deletes**: All major models use soft deletes.

## Functional Requirements

### REQ-1: Multi-Company (Multi-Société)
- Users belong to many companies via pivot table `company_user` (no role column — roles are assigned separately via `role_user` pivot).
- Each user has a `current_company_id` to track active company.
- Company switcher (header `X-Company-Id` or via switch endpoint).
- All tenant data is scoped by `company_id` using a global scope trait.
- Company has: name, legal_name, tax_id, registration_number, email, phone, address, currency, fiscal year, logo, settings (jsonb).

### REQ-2: Users & Authentication
- User has: matricule (auto-generated `EMP-{YYYY}-{00001}`), first_name, last_name, email, password, phone, avatar, is_active. NO is_superadmin column — super admin is just a role like any other.
- Sanctum token auth with 24h expiry.
- Login, register, logout, refresh token, change password, forgot/reset password.
- Rate limiting: 5 login attempts/min.
- Password policy: min 8 chars, mixed case, number, special char.
- Password history (prevent reuse of last 5).
- Account lockout after 5 failed attempts (15 min).
- Audit: last_login_at, last_login_ip.

### REQ-3: Roles & Permissions (RBAC)
- **3 core tables**: `users`, `roles`, `permissions` with two pivot tables: `role_user` (M2M) and `permission_role` (M2M).
- Users have multiple Roles. Each Role has multiple Permissions. Permission check = collect all user's roles → collect all permissions from those roles → check if target permission exists.
- **NO direct user permission overrides** — permissions are ONLY assigned to roles, never directly to users.
- **NO role column on users table** — the relationship is purely M2M via `role_user` pivot.
- Roles are company-scoped: the `role_user` pivot includes `company_id` so a user can have different roles per company.
- Permissions follow pattern `{module}.{action}` (e.g., `invoices.create`).
- Standard actions per module: view_any, view, create, update, delete, restore, force_delete, export, import, print.
- Module-specific extra actions (e.g., quotes: send, accept, reject, convert, duplicate).
- 5 default roles: Super Admin, Admin, Manager, User, Viewer.
- ~480+ permissions total across all modules.
- `CheckPermission` middleware: `permission:{slug}` — checks if any of the user's roles (in current company) has the required permission.
- In Blade views: use `@can('invoices.create')` directive. In controllers: use `$this->authorize('create', Invoice::class)` or middleware.

### REQ-4: Personnel (HR)
- Personnel linked to User via matricule (optional — not all personnel are system users).
- Personnel has: personal info, department, position, hire/termination dates, employment type, salary, bank info, emergency contact, photo, status.
- Departments with hierarchy (self-referencing parent_id), manager.
- Positions within departments with salary range.
- Contracts: type (CDI, CDD, stage, freelance, interim), dates, salary, trial period, working hours, benefits, documents.
- Leaves: types (annual, sick, maternity, paternity, unpaid, compensatory), date range, approval workflow (pending → approved/rejected), balance tracking.
- Attendance: daily check-in/out, total hours, overtime, status (present, absent, late, half_day, remote, holiday).

### REQ-5: Sales Module
- **Customers**: code, type (individual/company), contact info, tax_id, ICE, payment terms, credit limit, balance.
- **Quotes (Devis)**: reference, customer, date, validity, lines (product, description, qty, unit price, discount, tax), totals (HT, tax, TTC). Lifecycle: draft → sent → accepted/rejected/expired → converted to order.
- **Sales Orders (BC)**: similar to quotes, from quote or standalone. Lifecycle: draft → confirmed → in_progress → delivered → invoiced. Tracks delivered/invoiced qty per line.
- **Invoices (Factures)**: from order or standalone. Lifecycle: draft → sent → partial/paid/overdue. Tracks amount paid/due. Supports partial payments.
- **Credit Notes (Avoirs)**: linked to invoice, with lines, reason. Lifecycle: draft → confirmed → applied.
- **Delivery Notes (BL)**: linked to sales order. Lifecycle: draft → ready → shipped → delivered → returned. Tracks carrier and tracking.
- All documents support PDF generation and auto-reference numbering.

### REQ-6: Purchasing Module
- **Suppliers**: same fields as customers but for suppliers, with bank info.
- **Purchase Requests (DA)**: internal request with lines, priority, approval workflow.
- **Purchase Orders (BCA)**: from PR or standalone, sent to supplier. Tracks received/invoiced qty per line.
- **Reception Notes (BR)**: linked to PO, records received vs ordered qty, rejected qty with reason. Confirmation triggers stock movement.
- **Purchase Invoices (FAF)**: linked to PO, same structure as sales invoices but for supplier.

### REQ-7: Inventory Module
- **Products**: code, barcode, name, category, type (product/service/consumable), purchase/sale/cost prices, tax, stock levels (min, max, reorder point), dimensions, stockable flag.
- **Product Categories**: hierarchical (parent_id).
- **Warehouses**: code, name, address, manager, default flag.
- **Stock Movements**: type (in, out, transfer, adjustment, return, initial), polymorphic source (ReceptionNote, DeliveryNote, etc.), warehouse, date. Each movement updates `stock_levels` table.
- **Stock Levels**: cached qty on hand, reserved, available per product per warehouse.
- **Physical Inventory**: count sheets with theoretical vs counted qty, validation generates adjustment movements.

### REQ-8: Finance Module
- **Currencies**: code, symbol, exchange rate.
- **Taxes**: TVA rates (20%, 14%, 10%, 7%, exempt).
- **Payment Terms**: name + days.
- **Payment Methods**: cash, check, bank transfer, credit card, mobile, other.
- **Chart of Accounts**: hierarchical, types (asset, liability, equity, revenue, expense).
- **Bank Accounts**: name, bank, account number, IBAN, SWIFT, currency, balance.
- **Journal Entries**: double-entry with lines (debit/credit per account). Validation: total debits = total credits. Lifecycle: draft → posted.
- **Payments**: incoming (customer) or outgoing (supplier), polymorphic payable (Invoice/PurchaseInvoice). Confirmation creates journal entry and updates bank balance.

### REQ-9: Caution (Deposit/Guarantee) Module
- **Caution Types**: configurable types with default percentage.
- **Cautions**: direction (given/received), partner (customer/supplier/other), polymorphic related (Contract, PO, SO, Project), amount, dates (issue/expiry/return), bank info, document.
- Lifecycle: draft → active → partially_returned/returned/expired/forfeited.
- Partial and full return tracking with history log.
- Expiry alerts (configurable days ahead).
- Dashboard stats: totals given/received, active count.

### REQ-10: Events Module
- Event categories with color coding.
- Events: title, type (meeting, conference, training, workshop, social, holiday), location, dates, recurring support (jsonb pattern), budget tracking, mandatory flag.
- Lifecycle: planned → confirmed → in_progress → completed/cancelled/postponed.
- Participants: internal (personnel/user) or external (name/email), role (organizer, speaker, attendee, guest), RSVP status.

### REQ-11: CRM Module
- **Contacts**: linked to customer/supplier, source tracking, tags.
- **Leads**: reference, contact, source, estimated value, probability, assignment. Lifecycle: new → contacted → qualified/unqualified → converted/lost.
- **Opportunities**: from lead or standalone, customer, value/probability/weighted value, stages (prospecting → qualification → proposal → negotiation → closed_won/lost). Links to won quote.
- **Activities**: polymorphic (contact, lead, opportunity, customer), types (call, email, meeting, task, note, follow_up), due date, completion, priority.

### REQ-12: Project Management Module
- **Projects**: reference, name, customer, manager, dates, budget, progress (0-100), priority, status (draft → planned → in_progress → completed).
- **Tasks**: hierarchical (parent_id), assigned to user, dates, estimated/actual hours, priority, status (todo → in_progress → review → done).
- **Timesheets**: project + task, personnel, date, hours, billable flag, hourly rate, approval workflow.

### REQ-13: Settings & Configuration
- **Settings**: company-scoped key-value pairs grouped by category.
- **Sequences**: auto-numbering per model per company with prefix, padding, yearly/monthly reset.
- **Audit Logs**: every create/update/delete logged with old/new values, user, IP, URL.
- **Notifications**: in-app notifications with read/unread, polymorphic related.
- **Attachments**: polymorphic file uploads on any model.

### REQ-14: Frontend (Blade + Tailwind)
- Authenticated layout: sidebar navigation (grouped by module), top bar (company switcher, user menu, notification bell).
- Guest layout: login, register, password reset.
- Reusable Blade components: alert, badge, button, card, data-table, dropdown, form-input, form-select, modal, pagination, status-badge, stats-card, breadcrumb.
- Dashboard with stats widgets per module.
- Responsive design.

### REQ-15: Security
- HTTPS enforcement in production.
- CSRF on web routes.
- XSS sanitization on all inputs.
- SQL injection prevention (parameterized Eloquent).
- CORS configuration.
- File upload validation (type, size, mime).
- Rate limiting: API 60 req/min, login 5/min, upload 10/min, export 5/min.
- Encrypted sensitive fields.
- Tenant isolation verification in every query.

### REQ-16: Testing
- TDD approach: write tests before implementation.
- Feature tests: CRUD, lifecycle, authorization, tenant isolation for every module.
- Unit tests: service methods, calculations, validations.
- Base TestCase with company/user/token helpers.
- Factories for every model.
- ~200+ test cases total.

## Non-Functional Requirements

- All responses use API Resources for consistent JSON structure.
- Pagination on all list endpoints (default 15 per page).
- Filterable and sortable on all index endpoints.
- Searchable on key fields.
- All dates stored in UTC, displayed in company timezone.
- Currency amounts use decimal(15,2).
- Reference numbers auto-generated per company per year.
