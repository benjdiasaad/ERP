<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Sales\Customer;
use App\Services\Sales\CustomerService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerService();
    }

    // ─── Code generation ──────────────────────────────────────────────────────

    public function test_generated_code_matches_expected_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Acme Corp',
            'type'       => 'company',
        ]);

        $year = now()->year;
        $this->assertMatchesRegularExpression(
            '/^CUST-' . $year . '-\d{5}$/',
            $customer->code
        );
    }

    public function test_provided_code_is_not_overwritten(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Custom Code Corp',
            'type'       => 'company',
            'code'       => 'MY-CUSTOM-001',
        ]);

        $this->assertSame('MY-CUSTOM-001', $customer->code);
    }

    public function test_sequential_codes_are_incremented(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $year = now()->year;

        $first = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'First Customer',
            'type'       => 'company',
        ]);

        $second = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Second Customer',
            'type'       => 'company',
        ]);

        $this->assertSame("CUST-{$year}-00001", $first->code);
        $this->assertSame("CUST-{$year}-00002", $second->code);
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function test_create_persists_customer_to_database(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Test Customer',
            'type'       => 'individual',
            'email'      => 'test@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'id'    => $customer->id,
            'name'  => 'Test Customer',
            'email' => 'test@example.com',
        ]);
    }

    public function test_update_changes_customer_fields(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Old Name',
            'type'       => 'company',
        ]);

        $updated = $this->service->update($customer, ['name' => 'New Name', 'city' => 'Casablanca']);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('Casablanca', $updated->city);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name']);
    }

    public function test_delete_soft_deletes_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'To Delete',
            'type'       => 'company',
        ]);

        $result = $this->service->delete($customer);

        $this->assertTrue($result);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function test_search_returns_paginated_results(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->create(['company_id' => $company->id, 'name' => 'Alpha Corp', 'type' => 'company']);
        $this->service->create(['company_id' => $company->id, 'name' => 'Beta Ltd', 'type' => 'company']);

        $results = $this->service->search([]);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $results);
        $this->assertSame(2, $results->total());
    }

    public function test_search_filters_by_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->create(['company_id' => $company->id, 'name' => 'Alpha Corp', 'type' => 'company']);
        $this->service->create(['company_id' => $company->id, 'name' => 'Beta Ltd', 'type' => 'company']);

        $results = $this->service->search(['search' => 'Alpha']);

        $this->assertSame(1, $results->total());
        $this->assertSame('Alpha Corp', $results->items()[0]->name);
    }

    public function test_search_filters_by_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->create(['company_id' => $company->id, 'name' => 'Corp A', 'type' => 'company']);
        $this->service->create(['company_id' => $company->id, 'name' => 'John Doe', 'type' => 'individual']);

        $results = $this->service->search(['type' => 'individual']);

        $this->assertSame(1, $results->total());
        $this->assertSame('individual', $results->items()[0]->type);
    }

    public function test_search_filters_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->create(['company_id' => $company->id, 'name' => 'Active Co', 'type' => 'company', 'is_active' => true]);
        $this->service->create(['company_id' => $company->id, 'name' => 'Inactive Co', 'type' => 'company', 'is_active' => false]);

        $results = $this->service->search(['is_active' => true]);

        $this->assertSame(1, $results->total());
        $this->assertSame('Active Co', $results->items()[0]->name);
    }

    public function test_search_default_per_page_is_15(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $results = $this->service->search([]);

        $this->assertSame(15, $results->perPage());
    }

    // ─── Balance update ───────────────────────────────────────────────────────

    public function test_update_balance_add_increases_balance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Balance Test',
            'type'       => 'company',
            'balance'    => 100.00,
        ]);

        $updated = $this->service->updateBalance($customer, 50.00, 'add');

        $this->assertEquals(150.00, (float) $updated->balance);
    }

    public function test_update_balance_subtract_decreases_balance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Balance Test',
            'type'       => 'company',
            'balance'    => 200.00,
        ]);

        $updated = $this->service->updateBalance($customer, 75.00, 'subtract');

        $this->assertEquals(125.00, (float) $updated->balance);
    }

    public function test_update_balance_invalid_operation_throws_exception(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id' => $company->id,
            'name'       => 'Balance Test',
            'type'       => 'company',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updateBalance($customer, 50.00, 'multiply');
    }

    // ─── Credit check ─────────────────────────────────────────────────────────

    public function test_check_credit_returns_true_when_credit_limit_is_zero(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id'   => $company->id,
            'name'         => 'Unlimited Credit',
            'type'         => 'company',
            'credit_limit' => 0,
            'balance'      => 99999.00,
        ]);

        $this->assertTrue($this->service->checkCredit($customer, 999999.00));
    }

    public function test_check_credit_returns_true_when_within_limit(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id'   => $company->id,
            'name'         => 'Limited Credit',
            'type'         => 'company',
            'credit_limit' => 1000.00,
            'balance'      => 400.00,
        ]);

        $this->assertTrue($this->service->checkCredit($customer, 600.00));
    }

    public function test_check_credit_returns_false_when_exceeds_limit(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id'   => $company->id,
            'name'         => 'Over Limit',
            'type'         => 'company',
            'credit_limit' => 1000.00,
            'balance'      => 800.00,
        ]);

        $this->assertFalse($this->service->checkCredit($customer, 300.00));
    }

    // ─── Credit info ──────────────────────────────────────────────────────────

    public function test_get_credit_info_returns_correct_structure(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id'   => $company->id,
            'name'         => 'Credit Info Test',
            'type'         => 'company',
            'credit_limit' => 5000.00,
            'balance'      => 1500.00,
        ]);

        $info = $this->service->getCreditInfo($customer);

        $this->assertArrayHasKey('credit_limit', $info);
        $this->assertArrayHasKey('balance', $info);
        $this->assertArrayHasKey('available_credit', $info);
        $this->assertArrayHasKey('has_credit_limit', $info);
        $this->assertArrayHasKey('is_over_limit', $info);

        $this->assertEquals(5000.00, $info['credit_limit']);
        $this->assertEquals(1500.00, $info['balance']);
        $this->assertEquals(3500.00, $info['available_credit']);
        $this->assertTrue($info['has_credit_limit']);
        $this->assertFalse($info['is_over_limit']);
    }

    public function test_get_credit_info_unlimited_when_credit_limit_is_zero(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id'   => $company->id,
            'name'         => 'Unlimited',
            'type'         => 'company',
            'credit_limit' => 0,
            'balance'      => 500.00,
        ]);

        $info = $this->service->getCreditInfo($customer);

        $this->assertFalse($info['has_credit_limit']);
        $this->assertNull($info['available_credit']);
        $this->assertFalse($info['is_over_limit']);
    }

    public function test_get_credit_info_is_over_limit_when_balance_exceeds_limit(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = $this->service->create([
            'company_id'   => $company->id,
            'name'         => 'Over Limit Customer',
            'type'         => 'company',
            'credit_limit' => 1000.00,
            'balance'      => 1200.00,
        ]);

        $info = $this->service->getCreditInfo($customer);

        $this->assertTrue($info['is_over_limit']);
        $this->assertEquals(0.0, $info['available_credit']);
    }
}
