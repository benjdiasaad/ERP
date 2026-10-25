<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\CompanySwitchController;
use App\Http\Controllers\Web\CRM\ActivityController;
use App\Http\Controllers\Web\CRM\ContactController;
use App\Http\Controllers\Web\CRM\LeadController;
use App\Http\Controllers\Web\CRM\OpportunityController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Sales\CustomerController;
use App\Http\Controllers\Web\Sales\QuoteController;
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
        });
    });
});
