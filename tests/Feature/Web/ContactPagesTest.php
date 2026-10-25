<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\CRM\Contact;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class ContactPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_lists_only_current_company_contacts_and_filters(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['contacts.view_any']);
        Contact::factory()->create(['company_id' => $company->id, 'last_name' => 'Bennani']);
        Contact::factory()->create(['company_id' => $company->id, 'last_name' => 'Zeroual']);
        Contact::factory()->create(['last_name' => 'Bennani']); // other company

        $this->actingAs($user)->get('/crm/contacts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Contacts/Index')
                ->has('contacts.data', 2)
                ->has('contacts.meta'));

        $this->actingAs($user)->get('/crm/contacts?search=benn')
            ->assertInertia(fn (Assert $page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.last_name', 'Bennani')
                ->where('filters.search', 'benn'));
    }

    public function test_index_requires_permission(): void
    {
        ['user' => $user] = $this->actingWithPermissions([]);

        $this->actingAs($user)->get('/crm/contacts')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
    }

    public function test_can_create_contact(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['contacts.create', 'contacts.view']);

        $this->actingAs($user)->get('/crm/contacts/create')
            ->assertInertia(fn (Assert $page) => $page->component('CRM/Contacts/Create')->has('sources'));

        $response = $this->actingAs($user)->post('/crm/contacts', [
            'first_name' => 'Mehdi',
            'last_name'  => 'Lahlou',
            'email'      => 'mehdi@example.ma',
            'is_active'  => true,
        ]);

        $contact = Contact::where('email', 'mehdi@example.ma')->firstOrFail();
        $response->assertRedirect(route('crm.contacts.show', $contact))->assertSessionHas('success');
        $this->assertEquals($company->id, $contact->company_id);
    }

    public function test_create_validates_input(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['contacts.create']);

        $this->actingAs($user)->from('/crm/contacts/create')
            ->post('/crm/contacts', ['email' => 'not-an-email'])
            ->assertRedirect('/crm/contacts/create')
            ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
    }

    public function test_show_update_and_delete(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->modulePermissions('contacts'));
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->get("/crm/contacts/{$contact->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Contacts/Show')
                ->where('contact.data.id', $contact->id)
                ->has('leads')->has('opportunities')->has('activities'));

        $this->actingAs($user)->put("/crm/contacts/{$contact->id}", ['job_title' => 'CFO'])
            ->assertRedirect(route('crm.contacts.show', $contact));
        $this->assertEquals('CFO', $contact->fresh()->job_title);

        $this->actingAs($user)->delete("/crm/contacts/{$contact->id}")
            ->assertRedirect(route('crm.contacts.index'));
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_cannot_open_another_companys_contact(): void
    {
        ['user' => $user] = $this->actingWithPermissions($this->modulePermissions('contacts'));
        $foreign = Contact::factory()->create();

        $this->actingAs($user)->get("/crm/contacts/{$foreign->id}")
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));

        $this->actingAs($user)->delete("/crm/contacts/{$foreign->id}")->assertNotFound();
        $this->assertNotSoftDeleted('contacts', ['id' => $foreign->id]);
    }
}
