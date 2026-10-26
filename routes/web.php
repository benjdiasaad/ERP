<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Caution\CautionController;
use App\Http\Controllers\Web\Caution\CautionTypeController;
use App\Http\Controllers\Web\CompanySwitchController;
use App\Http\Controllers\Web\CRM\ActivityController;
use App\Http\Controllers\Web\CRM\ContactController;
use App\Http\Controllers\Web\CRM\LeadController;
use App\Http\Controllers\Web\CRM\OpportunityController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Finance\BankAccountController;
use App\Http\Controllers\Web\Finance\ChartOfAccountController;
use App\Http\Controllers\Web\Finance\JournalEntryController;
use App\Http\Controllers\Web\Inventory\ProductCategoryController;
use App\Http\Controllers\Web\Inventory\ProductController as InventoryProductController;
use App\Http\Controllers\Web\Inventory\StockInventoryController;
use App\Http\Controllers\Web\Inventory\StockLevelController;
use App\Http\Controllers\Web\Inventory\StockMovementController;
use App\Http\Controllers\Web\Inventory\WarehouseController;
use App\Http\Controllers\Web\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Web\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Web\Purchasing\PurchaseRequestController;
use App\Http\Controllers\Web\Purchasing\ReceptionNoteController;
use App\Http\Controllers\Web\Purchasing\SupplierController;
use App\Http\Controllers\Web\Sales\CreditNoteController;
use App\Http\Controllers\Web\Sales\CustomerController;
use App\Http\Controllers\Web\Sales\DeliveryNoteController;
use App\Http\Controllers\Web\Sales\InvoiceController;
use App\Http\Controllers\Web\Sales\QuoteController;
use App\Http\Controllers\Web\Sales\SalesOrderController;
use Illuminate\Support\Facades\Route;

/*
| Web app (Inertia + Vue). Session-authenticated; the JSON API lives in routes/api.php.
| Route names are prefixed by area (crm.contacts.*) so they don't collide with API names.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('company/switch', CompanySwitchController::class)->name('company.switch');

    Route::middleware('company')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::prefix('crm')->name('crm.')->group(function () {
            Route::resource('contacts', ContactController::class)
                ->middlewareFor('index', 'permission:contacts.view_any')
                ->middlewareFor('show', 'permission:contacts.view')
                ->middlewareFor(['create', 'store'], 'permission:contacts.create')
                ->middlewareFor(['edit', 'update'], 'permission:contacts.update')
                ->middlewareFor('destroy', 'permission:contacts.delete');

            Route::resource('leads', LeadController::class)
                ->middlewareFor('index', 'permission:leads.view_any')
                ->middlewareFor('show', 'permission:leads.view')
                ->middlewareFor(['create', 'store'], 'permission:leads.create')
                ->middlewareFor(['edit', 'update'], 'permission:leads.update')
                ->middlewareFor('destroy', 'permission:leads.delete');
            Route::post('leads/{lead}/qualify', [LeadController::class, 'qualify'])->middleware('permission:leads.qualify')->name('leads.qualify');
            Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('permission:leads.convert')->name('leads.convert');
            Route::post('leads/{lead}/assign', [LeadController::class, 'assign'])->middleware('permission:leads.assign')->name('leads.assign');

            Route::resource('opportunities', OpportunityController::class)
                ->middlewareFor('index', 'permission:opportunities.view_any')
                ->middlewareFor('show', 'permission:opportunities.view')
                ->middlewareFor(['create', 'store'], 'permission:opportunities.create')
                ->middlewareFor(['edit', 'update'], 'permission:opportunities.update')
                ->middlewareFor('destroy', 'permission:opportunities.delete');
            Route::post('opportunities/{opportunity}/advance', [OpportunityController::class, 'advance'])->middleware('permission:opportunities.advance')->name('opportunities.advance');
            Route::post('opportunities/{opportunity}/win', [OpportunityController::class, 'win'])->middleware('permission:opportunities.win')->name('opportunities.win');
            Route::post('opportunities/{opportunity}/lose', [OpportunityController::class, 'lose'])->middleware('permission:opportunities.lose')->name('opportunities.lose');

            Route::resource('activities', ActivityController::class)->except('show')
                ->middlewareFor('index', 'permission:activities.view_any')
                ->middlewareFor(['create', 'store'], 'permission:activities.create')
                ->middlewareFor(['edit', 'update'], 'permission:activities.update')
                ->middlewareFor('destroy', 'permission:activities.delete');
            Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->middleware('permission:activities.complete')->name('activities.complete');
        });

        Route::prefix('sales')->name('sales.')->group(function () {
            Route::resource('customers', CustomerController::class)
                ->middlewareFor('index', 'permission:customers.view_any')
                ->middlewareFor('show', 'permission:customers.view')
                ->middlewareFor(['create', 'store'], 'permission:customers.create')
                ->middlewareFor(['edit', 'update'], 'permission:customers.update')
                ->middlewareFor('destroy', 'permission:customers.delete');

            Route::resource('quotes', QuoteController::class)
                ->middlewareFor('index', 'permission:quotes.view_any')
                ->middlewareFor('show', 'permission:quotes.view')
                ->middlewareFor(['create', 'store'], 'permission:quotes.create')
                ->middlewareFor(['edit', 'update'], 'permission:quotes.update')
                ->middlewareFor('destroy', 'permission:quotes.delete');
            foreach (['send', 'accept', 'reject', 'convert', 'duplicate'] as $action) {
                Route::post("quotes/{quote}/{$action}", [QuoteController::class, $action])
                    ->middleware("permission:quotes.{$action}")
                    ->name("quotes.{$action}");
            }

            Route::resource('orders', SalesOrderController::class)
                ->parameters(['orders' => 'order'])
                ->middlewareFor('index', 'permission:sales_orders.view_any')
                ->middlewareFor('show', 'permission:sales_orders.view')
                ->middlewareFor(['create', 'store'], 'permission:sales_orders.create')
                ->middlewareFor(['edit', 'update'], 'permission:sales_orders.update')
                ->middlewareFor('destroy', 'permission:sales_orders.delete');
            foreach ([
                'confirm'                => 'confirm',
                'cancel'                 => 'cancel',
                'generate-invoice'       => 'generate_invoice',
                'generate-delivery-note' => 'generate_delivery_note',
            ] as $uri => $permission) {
                Route::post("orders/{order}/{$uri}", [SalesOrderController::class, str($uri)->camel()->toString()])
                    ->middleware("permission:sales_orders.{$permission}")
                    ->name("orders.{$uri}");
            }

            Route::resource('invoices', InvoiceController::class)
                ->middlewareFor('index', 'permission:invoices.view_any')
                ->middlewareFor('show', 'permission:invoices.view')
                ->middlewareFor(['create', 'store'], 'permission:invoices.create')
                ->middlewareFor(['edit', 'update'], 'permission:invoices.update')
                ->middlewareFor('destroy', 'permission:invoices.delete');
            foreach ([
                'send'               => 'send',
                'cancel'             => 'cancel',
                'record-payment'     => 'record_payment',
                'create-credit-note' => 'create_credit_note',
            ] as $uri => $permission) {
                Route::post("invoices/{invoice}/{$uri}", [InvoiceController::class, str($uri)->camel()->toString()])
                    ->middleware("permission:invoices.{$permission}")
                    ->name("invoices.{$uri}");
            }

            Route::resource('delivery-notes', DeliveryNoteController::class)
                ->parameters(['delivery-notes' => 'deliveryNote'])
                ->middlewareFor('index', 'permission:delivery_notes.view_any')
                ->middlewareFor('show', 'permission:delivery_notes.view')
                ->middlewareFor(['create', 'store'], 'permission:delivery_notes.create')
                ->middlewareFor(['edit', 'update'], 'permission:delivery_notes.update')
                ->middlewareFor('destroy', 'permission:delivery_notes.delete');
            foreach (['ready' => 'update', 'ship' => 'ship', 'deliver' => 'deliver', 'return' => 'return'] as $action => $permission) {
                Route::post("delivery-notes/{deliveryNote}/{$action}", [DeliveryNoteController::class, $action])
                    ->middleware("permission:delivery_notes.{$permission}")
                    ->name("delivery-notes.{$action}");
            }

            Route::resource('credit-notes', CreditNoteController::class)
                ->parameters(['credit-notes' => 'creditNote'])
                ->middlewareFor('index', 'permission:credit_notes.view_any')
                ->middlewareFor('show', 'permission:credit_notes.view')
                ->middlewareFor(['create', 'store'], 'permission:credit_notes.create')
                ->middlewareFor(['edit', 'update'], 'permission:credit_notes.update')
                ->middlewareFor('destroy', 'permission:credit_notes.delete');
            foreach (['confirm', 'apply'] as $action) {
                Route::post("credit-notes/{creditNote}/{$action}", [CreditNoteController::class, $action])
                    ->middleware("permission:credit_notes.{$action}")
                    ->name("credit-notes.{$action}");
            }
        });

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::resource('bank-accounts', BankAccountController::class)
                ->parameters(['bank-accounts' => 'bankAccount'])
                ->middlewareFor('index', 'permission:bank_accounts.view_any')
                ->middlewareFor('show', 'permission:bank_accounts.view')
                ->middlewareFor(['create', 'store'], 'permission:bank_accounts.create')
                ->middlewareFor(['edit', 'update'], 'permission:bank_accounts.update')
                ->middlewareFor('destroy', 'permission:bank_accounts.delete');

            Route::resource('accounts', ChartOfAccountController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->middlewareFor('index', 'permission:chart_of_accounts.view_any')
                ->middlewareFor('store', 'permission:chart_of_accounts.create')
                ->middlewareFor('update', 'permission:chart_of_accounts.update')
                ->middlewareFor('destroy', 'permission:chart_of_accounts.delete');

            Route::resource('journal-entries', JournalEntryController::class)
                ->parameters(['journal-entries' => 'journalEntry'])
                ->middlewareFor('index', 'permission:journal_entries.view_any')
                ->middlewareFor('show', 'permission:journal_entries.view')
                ->middlewareFor(['create', 'store'], 'permission:journal_entries.create')
                ->middlewareFor(['edit', 'update'], 'permission:journal_entries.update')
                ->middlewareFor('destroy', 'permission:journal_entries.delete');
            foreach (['post', 'cancel'] as $action) {
                Route::post("journal-entries/{journalEntry}/{$action}", [JournalEntryController::class, $action])
                    ->middleware("permission:journal_entries.{$action}")
                    ->name("journal-entries.{$action}");
            }

            Route::resource('caution-types', CautionTypeController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['caution-types' => 'type'])
                ->middlewareFor('index', 'permission:caution_types.view_any')
                ->middlewareFor('store', 'permission:caution_types.create')
                ->middlewareFor('update', 'permission:caution_types.update')
                ->middlewareFor('destroy', 'permission:caution_types.delete');

            Route::resource('cautions', CautionController::class)
                ->middlewareFor('index', 'permission:cautions.view_any')
                ->middlewareFor('show', 'permission:cautions.view')
                ->middlewareFor(['create', 'store'], 'permission:cautions.create')
                ->middlewareFor(['edit', 'update'], 'permission:cautions.update')
                ->middlewareFor('destroy', 'permission:cautions.delete');
            foreach ([
                'activate'       => 'activate',
                'partial-return' => 'partial_return',
                'full-return'    => 'full_return',
                'extend'         => 'extend',
                'forfeit'        => 'forfeit',
                'cancel'         => 'cancel',
            ] as $uri => $permission) {
                Route::post("cautions/{caution}/{$uri}", [CautionController::class, str($uri)->camel()->toString()])
                    ->middleware("permission:cautions.{$permission}")
                    ->name("cautions.{$uri}");
            }
        });

        Route::prefix('stock')->name('stock.')->group(function () {
            Route::resource('products', InventoryProductController::class)
                ->middlewareFor('index', 'permission:products.view_any')
                ->middlewareFor('show', 'permission:products.view')
                ->middlewareFor(['create', 'store'], 'permission:products.create')
                ->middlewareFor(['edit', 'update'], 'permission:products.update')
                ->middlewareFor('destroy', 'permission:products.delete');

            Route::resource('categories', ProductCategoryController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->middlewareFor('index', 'permission:product_categories.view_any')
                ->middlewareFor('store', 'permission:product_categories.create')
                ->middlewareFor('update', 'permission:product_categories.update')
                ->middlewareFor('destroy', 'permission:product_categories.delete');

            Route::resource('warehouses', WarehouseController::class)
                ->middlewareFor('index', 'permission:warehouses.view_any')
                ->middlewareFor('show', 'permission:warehouses.view')
                ->middlewareFor(['create', 'store'], 'permission:warehouses.create')
                ->middlewareFor(['edit', 'update'], 'permission:warehouses.update')
                ->middlewareFor('destroy', 'permission:warehouses.delete');

            Route::get('levels', [StockLevelController::class, 'index'])->middleware('permission:stock_levels.view_any')->name('levels.index');

            Route::get('movements', [StockMovementController::class, 'index'])->middleware('permission:stock_movements.view_any')->name('movements.index');
            Route::post('movements', [StockMovementController::class, 'store'])->middleware('permission:stock_movements.create')->name('movements.store');
            Route::post('movements/transfer', [StockMovementController::class, 'transfer'])->middleware('permission:stock_movements.transfer')->name('movements.transfer');

            Route::resource('inventories', StockInventoryController::class)
                ->except(['edit'])
                ->middlewareFor('index', 'permission:stock_inventories.view_any')
                ->middlewareFor('show', 'permission:stock_inventories.view')
                ->middlewareFor(['create', 'store'], 'permission:stock_inventories.create')
                ->middlewareFor('update', 'permission:stock_inventories.update')
                ->middlewareFor('destroy', 'permission:stock_inventories.delete');
            Route::post('inventories/{inventory}/start', [StockInventoryController::class, 'start'])->middleware('permission:stock_inventories.start')->name('inventories.start');
            Route::post('inventories/{inventory}/validate', [StockInventoryController::class, 'finalize'])->middleware('permission:stock_inventories.validate')->name('inventories.validate');
            Route::post('inventories/{inventory}/cancel', [StockInventoryController::class, 'cancel'])->middleware('permission:stock_inventories.cancel')->name('inventories.cancel');
        });

        Route::prefix('purchasing')->name('purchasing.')->group(function () {
            Route::resource('suppliers', SupplierController::class)
                ->middlewareFor('index', 'permission:suppliers.view_any')
                ->middlewareFor('show', 'permission:suppliers.view')
                ->middlewareFor(['create', 'store'], 'permission:suppliers.create')
                ->middlewareFor(['edit', 'update'], 'permission:suppliers.update')
                ->middlewareFor('destroy', 'permission:suppliers.delete');

            Route::resource('requests', PurchaseRequestController::class)
                ->parameters(['requests' => 'purchaseRequest'])
                ->middlewareFor('index', 'permission:purchase_requests.view_any')
                ->middlewareFor('show', 'permission:purchase_requests.view')
                ->middlewareFor(['create', 'store'], 'permission:purchase_requests.create')
                ->middlewareFor(['edit', 'update'], 'permission:purchase_requests.update')
                ->middlewareFor('destroy', 'permission:purchase_requests.delete');
            foreach (['submit', 'approve', 'reject', 'cancel', 'convert'] as $action) {
                Route::post("requests/{purchaseRequest}/{$action}", [PurchaseRequestController::class, $action])
                    ->middleware("permission:purchase_requests.{$action}")
                    ->name("requests.{$action}");
            }

            Route::resource('orders', PurchaseOrderController::class)
                ->parameters(['orders' => 'purchaseOrder'])
                ->middlewareFor('index', 'permission:purchase_orders.view_any')
                ->middlewareFor('show', 'permission:purchase_orders.view')
                ->middlewareFor(['create', 'store'], 'permission:purchase_orders.create')
                ->middlewareFor(['edit', 'update'], 'permission:purchase_orders.update')
                ->middlewareFor('destroy', 'permission:purchase_orders.delete');
            foreach ([
                'send'              => 'send',
                'confirm'           => 'confirm',
                'cancel'            => 'cancel',
                'generate-reception' => 'generate_reception',
                'generate-invoice'  => 'generate_invoice',
            ] as $uri => $permission) {
                Route::post("orders/{purchaseOrder}/{$uri}", [PurchaseOrderController::class, str($uri)->camel()->toString()])
                    ->middleware("permission:purchase_orders.{$permission}")
                    ->name("orders.{$uri}");
            }

            // Receptions are generated from a purchase order, so there is no create form.
            Route::resource('receptions', ReceptionNoteController::class)
                ->only(['index', 'show', 'update', 'destroy'])
                ->parameters(['receptions' => 'receptionNote'])
                ->middlewareFor('index', 'permission:reception_notes.view_any')
                ->middlewareFor('show', 'permission:reception_notes.view')
                ->middlewareFor('update', 'permission:reception_notes.update')
                ->middlewareFor('destroy', 'permission:reception_notes.delete');
            foreach (['confirm', 'cancel'] as $action) {
                Route::post("receptions/{receptionNote}/{$action}", [ReceptionNoteController::class, $action])
                    ->middleware("permission:reception_notes.{$action}")
                    ->name("receptions.{$action}");
            }

            Route::resource('invoices', PurchaseInvoiceController::class)
                ->parameters(['invoices' => 'purchaseInvoice'])
                ->middlewareFor('index', 'permission:purchase_invoices.view_any')
                ->middlewareFor('show', 'permission:purchase_invoices.view')
                ->middlewareFor(['create', 'store'], 'permission:purchase_invoices.create')
                ->middlewareFor(['edit', 'update'], 'permission:purchase_invoices.update')
                ->middlewareFor('destroy', 'permission:purchase_invoices.delete');
            foreach (['send' => 'send', 'cancel' => 'cancel', 'record-payment' => 'record_payment'] as $uri => $permission) {
                Route::post("invoices/{purchaseInvoice}/{$uri}", [PurchaseInvoiceController::class, str($uri)->camel()->toString()])
                    ->middleware("permission:purchase_invoices.{$permission}")
                    ->name("invoices.{$uri}");
            }
        });
    });
});
