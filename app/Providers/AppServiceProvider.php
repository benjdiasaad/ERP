<?php

namespace App\Providers;

use App\Models\Company\Company;
use App\Models\Personnel\Attendance;
use App\Models\Personnel\Contract;
use App\Models\Personnel\Department;
use App\Models\Personnel\Leave;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Policies\Company\CompanyPolicy;
use App\Policies\Personnel\AttendancePolicy;
use App\Policies\Personnel\ContractPolicy;
use App\Policies\Personnel\DepartmentPolicy;
use App\Policies\Personnel\LeavePolicy;
use App\Policies\Personnel\PersonnelPolicy;
use App\Policies\Personnel\PositionPolicy;
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
