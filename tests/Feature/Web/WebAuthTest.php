<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Company\Company;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_user_can_log_in_and_reach_dashboard(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'Password1!'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.currentCompanyId', $user->current_company_id));
    }

    public function test_wrong_password_is_rejected(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Password1!'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_shared_props_expose_only_current_company_permissions(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contacts.view_any']);

        $other = Company::factory()->create();
        $other->users()->attach($user->id, ['is_default' => false, 'joined_at' => now()]);
        $this->giveUserPermissions($user, $other, ['contacts.delete']);

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', ['contacts.view_any'])
                ->has('auth.companies', 2));
    }

    public function test_user_can_switch_to_own_company_only(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();
        $mine = Company::factory()->create();
        $mine->users()->attach($user->id, ['is_default' => false, 'joined_at' => now()]);
        $foreign = Company::factory()->create();

        $this->actingAs($user)->post('/company/switch', ['company_id' => $mine->id])
            ->assertRedirect(route('dashboard'));
        $this->assertEquals($mine->id, $user->fresh()->current_company_id);

        $this->actingAs($user)->post('/company/switch', ['company_id' => $foreign->id])
            ->assertSessionHasErrors('company_id');
        $this->assertEquals($mine->id, $user->fresh()->current_company_id);
    }
}
