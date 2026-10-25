<?php

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public const TEST_EMAIL = 'test@example.com';

    public const TEST_PASSWORD = 'Password1!';

    /**
     * Seed the application's database.
     *
     * Model events stay enabled: matricules, references and computed
     * fields are generated in model hooks.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $company = Company::firstOrCreate(
            ['name' => 'Atlas Trading SARL'],
            [
                'legal_name' => 'Atlas Trading SARL',
                'email'      => 'contact@atlas-trading.ma',
                'phone'      => '+212 522 000 000',
                'currency'   => 'MAD',
                'is_active'  => true,
            ]
        );

        $user = User::where('email', self::TEST_EMAIL)->first()
            ?? User::factory()->create([
                'name'       => 'Test User',
                'first_name' => 'Test',
                'last_name'  => 'User',
                'email'      => self::TEST_EMAIL,
                'password'   => Hash::make(self::TEST_PASSWORD),
            ]);

        self::attachToCompany($user, $company, 'super_admin', isDefault: true);

        $this->call([
            CurrencySeeder::class,
            TaxSeeder::class,
        ]);

        if (!app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }

        $this->command?->info('Login: ' . self::TEST_EMAIL . ' / ' . self::TEST_PASSWORD);
    }

    /**
     * Attach a user to a company with the given global role (idempotent).
     */
    public static function attachToCompany(User $user, Company $company, string $roleSlug, bool $isDefault = false): void
    {
        $company->users()->syncWithoutDetaching([
            $user->id => ['is_default' => $isDefault, 'joined_at' => now()],
        ]);

        $role = Role::where('slug', $roleSlug)->whereNull('company_id')->firstOrFail();

        DB::table('role_user')->insertOrIgnore([
            'role_id'    => $role->id,
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($isDefault || $user->current_company_id === null) {
            $user->forceFill(['current_company_id' => $company->id])->save();
        }
    }
}
