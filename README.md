# ERP

Multi-company ERP system built with Laravel 12, PostgreSQL, Tailwind CSS & Sanctum — featuring Sales, Purchasing, Inventory, Finance, HR, CRM, Projects, Events & Caution modules with granular RBAC permissions and full tenant isolation

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 (PHP 8.2+) |
| Database | PostgreSQL |
| Auth | Laravel Sanctum |
| Frontend | Blade + Tailwind CSS |
| Testing | PHPUnit (TDD) |
| Multi-tenancy | Single DB + `company_id` |

---

## Architecture

```
app/
├── Models/{Domain}/           # Eloquent models
├── Http/
│   ├── Controllers/{Domain}/  # Thin controllers → delegates to services
│   ├── Requests/{Domain}/     # FormRequest validation
│   ├── Resources/{Domain}/    # API Resources
│   └── Middleware/             # Auth, company, permission
├── Services/{Domain}/         # Business logic
├── Policies/{Domain}/         # Authorization
└── Traits/                    # BelongsToCompany, HasAuditTrail, etc.
```

**Domains:** Auth, Company, Personnel, Sales, Purchasing, Inventory, Finance, Caution, Event, CRM, Project, Settings

---

## Modules

### Core
| Module | Description |
|--------|-------------|
| Multi-Company | Users belong to N companies, each with isolated data |
| Users & Auth | Sanctum tokens, password policy, rate limiting, lockout |
| Roles & Permissions | 3-table RBAC — ~480+ permissions, company-scoped roles |

### Sales (Ventes)
| Module | Description |
|--------|-------------|
| Customers | Credit limits, payment terms |
| Quotes (Devis) | Draft → Sent → Accepted → Converted to Order |
| Sales Orders (BC) | Delivery & invoice generation |
| Invoices (Factures) | Partial payments, overdue tracking, PDF |
| Credit Notes (Avoirs) | Linked to invoices |
| Delivery Notes (BL) | Shipping & tracking |

### Purchasing (Achats)
| Module | Description |
|--------|-------------|
| Suppliers | Bank info, balance tracking |
| Purchase Requests (DA) | Approval workflow |
| Purchase Orders (BCA) | Tracks received/invoiced qty |
| Reception Notes (BR) | Triggers stock movements |
| Purchase Invoices (FAF) | Payment recording |

### Inventory (Stock)
| Module | Description |
|--------|-------------|
| Products | Categories, pricing, barcodes |
| Warehouses | Multiple per company |
| Stock Movements | In/Out/Transfer/Adjustment |
| Physical Inventory | Count → automatic adjustments |

### Finance
| Module | Description |
|--------|-------------|
| Chart of Accounts | Hierarchical (asset/liability/equity/revenue/expense) |
| Journal Entries | Double-entry, debit=credit validation |
| Payments | Auto journal entry + bank balance update |
| Taxes | TVA 20%, 14%, 10%, 7%, Exempt |
| Currencies | MAD, EUR, USD, GBP |

### Caution (Dépôts/Garanties)
| Module | Description |
|--------|-------------|
| Caution Management | Given/received, full lifecycle |
| Tracking | Partial return, extension, forfeiture |
| Alerts | Expiring caution notifications |

### HR (Personnel)
| Module | Description |
|--------|-------------|
| Personnel | Linked to users via matricule |
| Departments | Hierarchical with managers |
| Contracts | CDI, CDD, Stage, Freelance, Interim |
| Leaves | Request/approval + balance tracking |
| Attendance | Check-in/out, overtime |

### Events
| Module | Description |
|--------|-------------|
| Events | Meetings, trainings, workshops |
| Participants | Internal/external RSVP |
| Calendar | Recurring events, date queries |

### CRM
| Module | Description |
|--------|-------------|
| Contacts | Linked to customers/suppliers |
| Leads | Qualification pipeline |
| Opportunities | Stage tracking + forecasting |
| Activities | Calls, emails, meetings, tasks |

### Project Management
| Module | Description |
|--------|-------------|
| Projects | Budget, progress, customer link |
| Tasks | Hierarchical + assignment |
| Timesheets | Billable hours, approval flow |

---

## RBAC

```
users ←→ roles (M2M via role_user + company_id)
roles ←→ permissions (M2M via permission_role)
```

Users can have different roles per company. Permissions pattern: `{module}.{action}`

```php
// Code
$user->hasPermission('invoices.create');

// Blade
@if(auth()->user()->hasPermission('invoices.delete'))
    <button>Supprimer</button>
@endif

// Route middleware
Route::middleware('permission:invoices.create')->post('/invoices', ...);
```

**5 default roles:** Super Admin, Admin, Manager, User, Viewer

---

## Multi-Tenancy

`BelongsToCompany` trait on all tenant models:
- Auto-sets `company_id` on creation
- Global scope filters by current company
- Zero cross-company data leakage

---

## Reference Numbers

| Document | Format |
|----------|--------|
| Quote | DEV-2026-00001 |
| Sales Order | BC-2026-00001 |
| Invoice | FAC-2026-00001 |
| Credit Note | AV-2026-00001 |
| Delivery Note | BL-2026-00001 |
| Purchase Request | DA-2026-00001 |
| Purchase Order | BCA-2026-00001 |
| Reception Note | BR-2026-00001 |
| Purchase Invoice | FAF-2026-00001 |
| Payment | PAY-2026-00001 |
| Caution | CAU-2026-00001 |
| Journal Entry | JE-2026-00001 |
| Personnel | EMP-2026-00001 |

---

## Installation

```bash
git clone https://github.com/benjdiasaad/ERP.git
cd ERP

composer install
npm install

cp .env.example .env
php artisan key:generate

# Configure PostgreSQL in .env then:
php artisan migrate --seed
npm run build
php artisan serve
```

---

## Testing

```bash
php artisan test                          # All tests
php artisan test --filter=InvoiceTest     # Specific module
php artisan test --coverage               # With coverage
```

---

## Security

- Sanctum tokens (24h expiry)
- Password policy (8+ chars, mixed case, number, symbol)
- Account lockout (5 failed → 15 min lock)
- Rate limiting (login 5/min, API 60/min)
- CSRF + XSS protection
- Encrypted sensitive fields
- Full audit trail
- Tenant isolation on every query

---

## License

Proprietary — All rights reserved.
