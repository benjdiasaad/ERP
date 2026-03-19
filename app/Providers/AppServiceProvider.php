<?php

namespace App\Providers;

use App\Models\Company\Company;
use App\Models\Personnel\Attendance;
use App\Models\Personnel\Contract;
use App\Models\Personnel\Department;
use App\Models\Personnel\Leave;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\ReceptionNote;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Customer;
use App\Models\Sales\DeliveryNote;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quote;
use App\Models\Sales\SalesOrder;
use App\Policies\Company\CompanyPolicy;
use App\Policies\Sales\CreditNotePolicy;
use App\Policies\Sales\DeliveryNotePolicy;
use App\Policies\Inventory\ProductCategoryPolicy;
use App\Policies\Inventory\ProductPolicy;
use App\Policies\Personnel\AttendancePolicy;
use App\Policies\Purchasing\PurchaseRequestPolicy;
use App\Policies\Purchasing\PurchaseOrderPolicy;
use App\Policies\Purchasing\ReceptionNotePolicy;
use App\Policies\Purchasing\PurchaseInvoicePolicy;
use App\Policies\Personnel\ContractPolicy;
use App\Policies\Personnel\DepartmentPolicy;
use App\Policies\Personnel\LeavePolicy;
use App\Policies\Personnel\PersonnelPolicy;
use App\Policies\Personnel\PositionPolicy;
use App\Policies\Sales\CustomerPolicy;
use App\Policies\Sales\InvoicePolicy;
use App\Policies\Sales\QuotePolicy;
use App\Policies\Sales\SalesOrderPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Personnel::class, PersonnelPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(Leave::class, LeavePolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(ReceptionNote::class, ReceptionNotePolicy::class);
        Gate::policy(PurchaseInvoice::class, PurchaseInvoicePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(DeliveryNote::class, DeliveryNotePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);

        RateLimiter::for('login', fn (Request $request) =>
            Limit::perMinute(5)->by($request->ip())
        );

        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for('uploads', fn (Request $request) =>
            Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for('exports', fn (Request $request) =>
            Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())
        );
    }
}
