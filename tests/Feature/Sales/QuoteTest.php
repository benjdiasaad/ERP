<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\Quote;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name'       => 'Test Role',
            'slug'       => 'test-role-' . uniqid(),
            'is_system'  => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0], 'name' => $slug, 'description' => '']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id => ['company_id' => $company->id]]);
    }

    private function quotePayload(int $customerId): array
    {
        return [
            'customer_id'   => $customerId,
            'date'          => now()->toDateString(),
            'validity_date' => now()->addDays(30)->toDateString(),
            'lines'         => [
                [
                    'description'    => 'Service A',
                    'quantity'       => 2,
                    'unit_price_ht'  => 500.00,
                    'discount_type'  => null,
                    'discount_value' => 0,
                    'tax_rate'       => 20,
                ],
            ],
        ];
    }

    private function allQuotePermissions(): array
    {
        return [
            'quotes.view_any', 'quotes.view', 'quotes.create',
            'quotes.update', 'quotes.delete', 'quotes.send',
            'quotes.convert',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_quotes(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/quotes')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/quotes')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/quotes')
            ->assertForbidden();
    }

    public function test_index_only_returns_quotes_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['quotes.view_any']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        Quote::factory()->create([
            'company_id'  => $companyA->id,
            'customer_id' => $customerA->id,
            'reference'   => 'DEV-MY-00001',
        ]);

        $companyB  = Company::factory()->create();
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);
        Quote::factory()->create([
            'company_id'  => $companyB->id,
            'customer_id' => $customerB->id,
            'reference'   => 'DEV-OTHER-00001',
        ]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/quotes');

        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('DEV-MY-00001', $refs);
        $this->assertNotContains('DEV-OTHER-00001', $refs);
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        Quote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        Quote::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/quotes?status=draft');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['draft'], $statuses);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_quote(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $this->quotePayload($customer->id));

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'draft'])
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('quotes', ['company_id' => $company->id, 'customer_id' => $customer->id]);
    }

    public function test_store_auto_generates_reference(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $this->quotePayload($customer->id));

        $response->assertCreated();
        $ref = $response->json('data.reference');
        $this->assertNotNull($ref);
        $this->assertMatchesRegularExpression('/^DEV-\d{4}-\d{5}$/', $ref);
    }

    public function test_store_calculates_line_totals(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id'   => $customer->id,
            'date'          => now()->toDateString(),
            'validity_date' => now()->addDays(30)->toDateString(),
            'lines'         => [
                [
                    'description'    => 'Product X',
                    'quantity'       => 10,
                    'unit_price_ht'  => 100.00,
                    'discount_type'  => 'percentage',
                    'discount_value' => 10,
                    'tax_rate'       => 20,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $payload);

        $response->assertCreated();
        // subtotal_ht = 10 * 100 = 1000, discount = 100, after_discount = 900, tax = 180, ttc = 1080
        $this->assertEquals('1000.00', $response->json('data.subtotal_ht'));
        $this->assertEquals('100.00', $response->json('data.total_discount'));
        $this->assertEquals('180.00', $response->json('data.total_tax'));
        $this->assertEquals('1080.00', $response->json('data.total_ttc'));
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $this->quotePayload($customer->id))
            ->assertForbidden();
    }

    public function test_store_requires_customer_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.create']);

        $payload = [
            'date'          => now()->toDateString(),
            'validity_date' => now()->addDays(30)->toDateString(),
            'lines'         => [['description' => 'X', 'quantity' => 1, 'unit_price_ht' => 100]],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_requires_at_least_one_line(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id'   => $customer->id,
            'date'          => now()->toDateString(),
            'validity_date' => now()->addDays(30)->toDateString(),
            'lines'         => [],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_quote(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/quotes/{$quote->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $quote->id]);
    }

    public function test_show_returns_404_for_nonexistent_quote(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/quotes/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/quotes/{$quote->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_quote(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/quotes/{$quote->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/quotes/{$quote->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_quote(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.delete']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/quotes/{$quote->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/quotes/{$quote->id}")
            ->assertForbidden();
    }

    // ─── Lifecycle: Send ──────────────────────────────────────────────────────

    public function test_draft_quote_can_be_sent(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.send']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'sent']);
        $this->assertNotNull($quote->fresh()->sent_at);
    }

    public function test_sent_quote_cannot_be_sent_again(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.send']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/send")
            ->assertStatus(422);
    }

    // ─── Lifecycle: Accept ────────────────────────────────────────────────────

    public function test_sent_quote_can_be_accepted(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/accept")
            ->assertOk()
            ->assertJsonFragment(['status' => 'accepted']);

        $this->assertNotNull($quote->fresh()->accepted_at);
    }

    public function test_draft_quote_cannot_be_accepted(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/accept")
            ->assertStatus(422);
    }

    // ─── Lifecycle: Reject ────────────────────────────────────────────────────

    public function test_sent_quote_can_be_rejected(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/reject", ['rejection_reason' => 'Price too high'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'rejected', 'rejection_reason' => 'Price too high']);

        $this->assertNotNull($quote->fresh()->rejected_at);
    }

    public function test_draft_quote_cannot_be_rejected(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/reject")
            ->assertStatus(422);
    }

    // ─── Lifecycle: Duplicate ─────────────────────────────────────────────────

    public function test_quote_can_be_duplicated(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->sent()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'notes'       => 'Original notes',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/duplicate")
            ->assertCreated();

        $newRef = $response->json('data.reference');
        $this->assertNotEquals($quote->reference, $newRef);
        $this->assertEquals('draft', $response->json('data.status'));
        $this->assertEquals('Original notes', $response->json('data.notes'));
    }

    public function test_duplicate_creates_new_quote_in_database(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $countBefore = Quote::count();

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/duplicate")
            ->assertCreated();

        $this->assertEquals($countBefore + 1, Quote::count());
    }

    // ─── Lifecycle: Convert to Order ──────────────────────────────────────────

    public function test_accepted_quote_can_be_converted_to_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.convert']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->accepted()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/convert-to-order")
            ->assertCreated();

        $this->assertArrayHasKey('sales_order', $response->json());
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'converted']);
    }

    public function test_non_accepted_quote_cannot_be_converted_to_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.convert']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quote->id}/convert-to-order")
            ->assertStatus(422);
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_quote_lifecycle_draft_to_converted(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allQuotePermissions());

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        // 1. Create (draft)
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $this->quotePayload($customer->id))
            ->assertCreated();

        $quoteId = $createResponse->json('data.id');
        $this->assertEquals('draft', $createResponse->json('data.status'));

        // 2. Send
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quoteId}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);

        // 3. Accept
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quoteId}/accept")
            ->assertOk()
            ->assertJsonFragment(['status' => 'accepted']);

        // 4. Convert to order
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quoteId}/convert-to-order")
            ->assertCreated();

        $this->assertDatabaseHas('quotes', ['id' => $quoteId, 'status' => 'converted']);
    }

    public function test_full_quote_lifecycle_draft_to_rejected(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allQuotePermissions());

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/quotes', $this->quotePayload($customer->id))
            ->assertCreated();

        $quoteId = $createResponse->json('data.id');

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quoteId}/send")
            ->assertOk();

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/quotes/{$quoteId}/reject", ['rejection_reason' => 'Budget cut'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'rejected', 'rejection_reason' => 'Budget cut']);
    }

    // ─── PDF ──────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_generate_pdf(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['quotes.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/quotes/{$quote->id}/pdf")
            ->assertOk()
            ->assertJsonStructure(['path', 'url']);
    }

    public function test_pdf_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $quote = Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/quotes/{$quote->id}/pdf")
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_quote_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['quotes.view']);
        $this->giveUserPermissions($userB, $companyB, ['quotes.view']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $quote     = Quote::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/quotes/{$quote->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_quote_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['quotes.update']);
        $this->giveUserPermissions($userB, $companyB, ['quotes.update']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $quote     = Quote::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/quotes/{$quote->id}", ['notes' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_quote_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['quotes.delete']);
        $this->giveUserPermissions($userB, $companyB, ['quotes.delete']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $quote     = Quote::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/quotes/{$quote->id}")
            ->assertNotFound();
    }

    public function test_index_does_not_leak_quotes_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['quotes.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['quotes.view_any']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);

        Quote::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id, 'reference' => 'DEV-A-00001']);
        Quote::factory()->create(['company_id' => $companyB->id, 'customer_id' => $customerB->id, 'reference' => 'DEV-B-00001']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/quotes');

        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('DEV-A-00001', $refs);
        $this->assertNotContains('DEV-B-00001', $refs);
    }

    public function test_user_from_company_b_cannot_send_quote_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['quotes.send']);
        $this->giveUserPermissions($userB, $companyB, ['quotes.send']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $quote     = Quote::factory()->draft()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/quotes/{$quote->id}/send")
            ->assertNotFound();
    }
}
