<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\Sales\Customer;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use InteractsWithCrm;

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function test_can_list_contacts(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['contacts.view_any']);
        Contact::factory()->count(3)->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'first_name', 'last_name', 'full_name', 'email']], 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/contacts')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions([]);

        $this->withHeaders($headers)->getJson('/api/contacts')->assertForbidden();
    }

    public function test_index_filters_by_search_case_insensitively(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['contacts.view_any']);
        Contact::factory()->create(['company_id' => $company->id, 'last_name' => 'Benali']);
        Contact::factory()->create(['company_id' => $company->id, 'last_name' => 'Zeroual', 'organization' => 'Acme']);

        $this->withHeaders($headers)->getJson('/api/contacts?search=BENA')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Benali');
    }

    public function test_can_create_contact(): void
    {
        ['user' => $user, 'company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['contacts.create']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Acme SARL']);

        $response = $this->withHeaders($headers)->postJson('/api/contacts', [
            'first_name'  => 'Amina',
            'last_name'   => 'Idrissi',
            'email'       => 'amina@example.com',
            'customer_id' => $customer->id,
            'source'      => 'referral',
            'tags'        => ['vip'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Amina Idrissi')
            ->assertJsonPath('data.tags', ['vip']);

        $this->assertDatabaseHas('contacts', [
            'email'      => 'amina@example.com',
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['contacts.create']);

        $this->withHeaders($headers)->postJson('/api/contacts', ['source' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'source']);
    }

    public function test_create_rejects_customer_from_another_company(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['contacts.create']);
        $foreignCustomer = Customer::factory()->create(['name' => 'Foreign SARL']);

        $this->withHeaders($headers)->postJson('/api/contacts', [
            'first_name'  => 'A',
            'last_name'   => 'B',
            'customer_id' => $foreignCustomer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer_id']);
    }

    public function test_can_show_update_and_delete_contact(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions($this->modulePermissions('contacts'));
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->getJson("/api/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id);

        $this->withHeaders($headers)->putJson("/api/contacts/{$contact->id}", ['job_title' => 'CFO'])
            ->assertOk()
            ->assertJsonPath('data.job_title', 'CFO');

        $this->withHeaders($headers)->deleteJson("/api/contacts/{$contact->id}")->assertNoContent();
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_contacts_are_isolated_between_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->actingWithPermissions(['contacts.view_any', 'contacts.view']);
        ['user' => $userB, 'headers' => $headersB] = $this->actingWithPermissions(['contacts.view_any', 'contacts.view']);
        $contact = Contact::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/contacts', $userA, $userB, $contact->id);
        $this->withHeaders($headersB)->getJson('/api/contacts')->assertOk()->assertJsonCount(0, 'data');
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function test_search_matches_name_email_and_organization(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['contacts.view_any']);
        Contact::factory()->create(['company_id' => $company->id, 'first_name' => 'Youssef', 'email' => 'y@a.com', 'organization' => null]);
        Contact::factory()->create(['company_id' => $company->id, 'first_name' => 'Sara', 'email' => 'sara@globex.ma', 'organization' => null]);
        Contact::factory()->create(['company_id' => $company->id, 'first_name' => 'Omar', 'email' => 'o@b.com', 'organization' => 'Globex SARL']);

        $this->withHeaders($headers)->getJson('/api/contacts/search?q=globex')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_search_requires_query(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['contacts.view_any']);

        $this->withHeaders($headers)->getJson('/api/contacts/search')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    // ─── Merge ────────────────────────────────────────────────────────────────

    public function test_merge_moves_related_records_and_deletes_duplicates(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['contacts.merge']);

        $target = Contact::factory()->create([
            'company_id' => $company->id, 'phone' => null, 'tags' => ['a'], 'notes' => null,
        ]);
        $duplicate = Contact::factory()->create([
            'company_id' => $company->id, 'phone' => '0600000000', 'tags' => ['a', 'b'], 'notes' => 'Met at fair',
        ]);

        $lead = Lead::factory()->create(['company_id' => $company->id, 'contact_id' => $duplicate->id]);
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id, 'contact_id' => $duplicate->id]);
        $activity = Activity::factory()->create([
            'company_id' => $company->id, 'related_type' => Contact::class, 'related_id' => $duplicate->id,
        ]);

        $this->withHeaders($headers)->postJson("/api/contacts/{$target->id}/merge", [
            'contact_ids' => [$duplicate->id],
        ])->assertOk()
            ->assertJsonPath('data.phone', '0600000000')
            ->assertJsonPath('data.tags', ['a', 'b'])
            ->assertJsonPath('data.notes', 'Met at fair');

        $this->assertSoftDeleted('contacts', ['id' => $duplicate->id]);
        $this->assertEquals($target->id, $lead->fresh()->contact_id);
        $this->assertEquals($target->id, $opportunity->fresh()->contact_id);
        $this->assertEquals($target->id, $activity->fresh()->related_id);
    }

    public function test_cannot_merge_contact_into_itself(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['contacts.merge']);
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/contacts/{$contact->id}/merge", [
            'contact_ids' => [$contact->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['contact_ids']);
    }

    public function test_cannot_merge_contacts_from_another_company(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['contacts.merge']);
        $target = Contact::factory()->create(['company_id' => $company->id]);
        $foreign = Contact::factory()->create();

        $this->withHeaders($headers)->postJson("/api/contacts/{$target->id}/merge", [
            'contact_ids' => [$foreign->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['contact_ids.0']);

        $this->assertNotSoftDeleted('contacts', ['id' => $foreign->id]);
    }

    public function test_merge_requires_merge_permission(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions($this->modulePermissions('contacts'));
        $target = Contact::factory()->create(['company_id' => $company->id]);
        $duplicate = Contact::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/contacts/{$target->id}/merge", [
            'contact_ids' => [$duplicate->id],
        ])->assertForbidden();
    }
}
