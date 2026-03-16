<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests for the CheckPermission middleware.
 *
 * A temporary test route protected by auth:sanctum + company + permission:invoices.create
 * is registered in setUp() so we can exercise the full middleware stack without
 * depending on any real application route.
 */
class PermissionTest extends TestCase
{
    private const TEST_ROUTE = '/test-permission-check';
    private const PERMISSION  = 'invoices.create';

    protected function setUp(): void
    {
        parent::setUp();

        // Register a throw-away route that requires the permission under test.
        Route::middleware(['auth:sanctum', 'company', 'permission:' . self::PERMISSION])
            ->get(self::TEST_ROUTE, fn () => response()->json(['ok' => true]));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Create a role, optionally attach permissions to it, and assign it to the
     * given user in the given company.
     *
     * @param  string[]  $permissionSlugs
     */
    private function createRoleWithPermissions(
        User    $user,
        Company $company,
        array   $permissionSlugs = []
    ): Role {
        $role = Role::create([
            'company_id'  => $company->id,
            'name'        => 'Test Role ' . uniqid(),
            'slug'        => 'test-role-' . uniqid(),
            'description' => 'Auto-created for permission tests',
            'is_system'   => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'module'      => explode('.', $slug)[0],
                    'name'        => $slug,
                    'description' => '',
                ]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([
            $role->id => ['company_id' => $company->id],
        ]);

        return $role;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    /**
     * A user whose role has the required permission must receive 200.
     */
    public function test_user_with_role_that_has_permission_gets_200(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->createRoleWithPermissions($user, $company, [self::PERMISSION]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson(self::TEST_ROUTE)
            ->assertOk();
    }

    /**
     * A user whose role does NOT have the required permission must receive 403.
     */
    public function test_user_with_role_without_permission_gets_403(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // Role exists but has a different permission — not invoices.create
        $this->createRoleWithPermissions($user, $company, ['invoices.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson(self::TEST_ROUTE)
            ->assertForbidden();
    }

    /**
     * A user with no roles at all must receive 403.
     */
    public function test_user_with_no_roles_gets_403(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();
        // No roles attached

        $this->withHeaders($this->authHeaders($user))
            ->getJson(self::TEST_ROUTE)
            ->assertForbidden();
    }

    /**
     * When a user has multiple roles and the required permission exists in only
     * one of them, the middleware must merge all role permissions and allow access.
     */
    public function test_user_with_multiple_roles_gets_200_when_one_role_has_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        // Role A — does NOT have the required permission
        $this->createRoleWithPermissions($user, $company, ['invoices.view']);

        // Role B — DOES have the required permission
        $this->createRoleWithPermissions($user, $company, [self::PERMISSION]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson(self::TEST_ROUTE)
            ->assertOk();
    }

    /**
     * A user who has a role in company A but sends X-Company-Id for company B
     * (to which they do not belong) must receive 403 — no cross-company permission leak.
     */
    public function test_user_with_role_in_company_a_cannot_access_as_company_b(): void
    {
        // Set up user in company A with the required permission
        ['user' => $user, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->createRoleWithPermissions($user, $companyA, [self::PERMISSION]);

        // Company B — user is NOT a member
        $companyB = Company::factory()->create();

        // Send request claiming to be in company B
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Company-Id'  => (string) $companyB->id,
            'Accept'        => 'application/json',
        ])->getJson(self::TEST_ROUTE)
          ->assertForbidden();
    }
}
