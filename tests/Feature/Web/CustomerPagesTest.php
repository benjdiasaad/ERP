<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Sales\Customer;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_lists_and_filters_company_customers(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['customers.view_any']);
        Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas Hotels', 'type' => 'company']);
        Customer::factory()->create(['company_id' => $company->id, 'name' => 'Karim Benali', 'type' => 'individual']);
        Customer::factory()->create(['name' => 'Foreign SARL']);

        $this->actingAs($user)->get('/sales/customers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sales/Customers/Index')->has('customers.data', 2));

        $this->actingAs($user)->get('/sales/customers?type=individual')
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Karim Benali'));
    }

    public function test_can_create_individual_customer_without_company_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['customers.create', 'customers.view']);

        $this->actingAs($user)->post('/sales/customers', [
            'type' => 'individual', 'first_name' => 'Salma', 'last_name' => 'Idrissi', 'is_active' => true,
            'credit_limit' => null, // the form sends an empty limit as null
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customer = Customer::where('last_name', 'Idrissi')->firstOrFail();
        $this->assertEquals('Salma Idrissi', $customer->name);
        $this->assertEquals('0.00', $customer->credit_limit);
        $this->assertEquals($company->id, $customer->company_id);
    }

    public function test_show_includes_credit_and_related_records(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['customers.view', 'quotes.view_any']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas', 'credit_limit' => 1000, 'balance' => 250]);

        $this->actingAs($user)->get("/sales/customers/{$customer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Customers/Show')
                ->where('credit.has_credit_limit', true)
                ->has('quotes')
                ->where('contacts', null)); // no contacts permission
    }

    public function test_cannot_open_another_companys_customer(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['customers.view']);
        $foreign = Customer::factory()->create(['name' => 'Foreign']);

        $this->actingAs($user)->get("/sales/customers/{$foreign->id}")->assertNotFound();
    }
}
