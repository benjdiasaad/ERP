<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Caution\Caution;
use App\Models\Caution\CautionType;
use App\Models\Finance\BankAccount;
use App\Models\Finance\ChartOfAccount;
use App\Models\Finance\Currency;
use App\Models\Finance\JournalEntry;
use App\Models\Sales\Customer;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class FinancePagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function allPermissions(): array
    {
        return [
            ...$this->modulePermissions('bank_accounts'),
            ...$this->modulePermissions('chart_of_accounts'),
            ...$this->modulePermissions('journal_entries', ['post', 'cancel']),
            ...$this->modulePermissions('caution_types'),
            ...$this->modulePermissions('cautions', ['activate', 'partial_return', 'full_return', 'extend', 'forfeit', 'cancel']),
        ];
    }

    public function test_index_pages_render_and_are_isolated(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $currency = Currency::factory()->create(['company_id' => null, 'code' => 'MAD']);
        BankAccount::factory()->create(['company_id' => $company->id, 'currency_id' => $currency->id]);
        $foreignAccount = BankAccount::factory()->create(['currency_id' => $currency->id]);
        $foreignEntry = JournalEntry::factory()->create();

        $pages = [
            '/finance/bank-accounts'   => 'Finance/BankAccounts/Index',
            '/finance/accounts'        => 'Finance/Accounts/Index',
            '/finance/journal-entries' => 'Finance/JournalEntries/Index',
            '/finance/caution-types'   => 'Caution/Types/Index',
            '/finance/cautions'        => 'Caution/Cautions/Index',
        ];
        foreach ($pages as $url => $component) {
            $this->actingAs($user)->get($url)->assertInertia(fn (Assert $page) => $page->component($component));
        }

        $this->actingAs($user)->get('/finance/bank-accounts')->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 1)->where('accounts.data.0.currency.code', 'MAD'));
        $this->actingAs($user)->get("/finance/bank-accounts/{$foreignAccount->id}")->assertNotFound();
        $this->actingAs($user)->get("/finance/journal-entries/{$foreignEntry->id}")->assertNotFound();
    }

    public function test_pages_require_permissions(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['bank_accounts.view_any']);

        $this->actingAs($user)->get('/finance/bank-accounts')->assertOk();
        $this->actingAs($user)->get('/finance/journal-entries')->assertForbidden();
        $this->actingAs($user)->get('/finance/cautions')->assertForbidden();
    }

    public function test_bank_account_balance_is_only_set_at_creation(): void
    {
        ['user' => $user] = $this->actingWithPermissions($this->allPermissions());
        $currency = Currency::factory()->create(['company_id' => null, 'code' => 'MAD']);

        $this->actingAs($user)->post('/finance/bank-accounts', [
            'name' => 'Compte courant', 'bank' => 'Attijariwafa bank', 'account_number' => '007780000123456789012345',
            'currency_id' => $currency->id, 'balance' => 15000, 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $account = BankAccount::firstOrFail();

        $this->actingAs($user)->put("/finance/bank-accounts/{$account->id}", ['name' => 'Compte principal', 'balance' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('Compte principal', $account->fresh()->name);
        $this->assertEquals('15000.00', $account->fresh()->balance);
    }

    public function test_chart_account_codes_are_unique_per_company_and_used_accounts_are_kept(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        ChartOfAccount::factory()->create(['code' => '5141']); // another company

        $this->actingAs($user)->post('/finance/accounts', ['code' => '5141', 'name' => 'Banque', 'type' => 'asset'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/finance/accounts', ['code' => '5141', 'name' => 'Banque bis', 'type' => 'asset'])
            ->assertSessionHasErrors('code');

        $bank = ChartOfAccount::where('company_id', $company->id)->where('code', '5141')->firstOrFail();
        $sales = ChartOfAccount::factory()->create(['company_id' => $company->id, 'code' => '7111', 'type' => 'revenue']);
        $this->actingAs($user)->post('/finance/journal-entries', ['date' => now()->toDateString(), 'lines' => [
            ['chart_of_account_id' => $bank->id, 'debit' => 100],
            ['chart_of_account_id' => $sales->id, 'credit' => 100],
        ]]);

        $this->actingAs($user)->delete("/finance/accounts/{$bank->id}")->assertSessionHasErrors('account');
        $this->assertNotSoftDeleted($bank);
    }

    public function test_journal_entry_is_posted_only_when_balanced(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $bank = ChartOfAccount::factory()->create(['company_id' => $company->id, 'code' => '5141', 'type' => 'asset']);
        $sales = ChartOfAccount::factory()->create(['company_id' => $company->id, 'code' => '7111', 'type' => 'revenue']);
        $foreign = ChartOfAccount::factory()->create();

        $this->actingAs($user)->post('/finance/journal-entries', ['date' => now()->toDateString(), 'lines' => [
            ['chart_of_account_id' => $foreign->id, 'debit' => 100],
        ]])->assertSessionHasErrors('lines.0.chart_of_account_id');

        $this->actingAs($user)->post('/finance/journal-entries', [
            'date' => now()->toDateString(), 'description' => 'Vente comptant',
            'lines' => [
                ['chart_of_account_id' => $bank->id, 'debit' => 1200],
                ['chart_of_account_id' => $sales->id, 'credit' => 1000],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $entry = JournalEntry::with('lines')->firstOrFail();
        $this->assertMatchesRegularExpression('/^EC-\d{4}-00001$/', $entry->reference);

        $this->actingAs($user)->post("/finance/journal-entries/{$entry->id}/post")->assertSessionHasErrors('lines');

        $this->actingAs($user)->put("/finance/journal-entries/{$entry->id}", ['lines' => [
            ['id' => $entry->lines[0]->id, 'chart_of_account_id' => $bank->id, 'debit' => 1200],
            ['id' => $entry->lines[1]->id, 'chart_of_account_id' => $sales->id, 'credit' => 1200],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/finance/journal-entries/{$entry->id}/post")->assertRedirect()->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals('1200.00', $entry->total_credit);
        $this->actingAs($user)->get("/finance/journal-entries/{$entry->id}/edit")->assertRedirect("/finance/journal-entries/{$entry->id}");
        $this->actingAs($user)->delete("/finance/journal-entries/{$entry->id}")->assertSessionHasErrors('status');
    }

    public function test_caution_lifecycle_tracks_returned_amounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $type = CautionType::factory()->create(['company_id' => $company->id, 'name' => 'Caution définitive']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $foreignCustomer = Customer::factory()->create();
        $payload = [
            'caution_type_id' => $type->id, 'direction' => 'given', 'partner_type' => 'customer', 'partner_id' => $customer->id,
            'amount' => 5000, 'issue_date' => now()->toDateString(), 'expiry_date' => now()->addMonths(6)->toDateString(),
        ];

        $this->actingAs($user)->post('/finance/cautions', ['partner_id' => $foreignCustomer->id] + $payload)->assertSessionHasErrors('partner_id');
        $this->actingAs($user)->post('/finance/cautions', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $caution = Caution::firstOrFail();
        $this->assertEquals(Customer::class, $caution->partner_type);

        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/activate")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/partial-return", ['amount' => 2000])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/partial-return", ['amount' => 4000])->assertSessionHasErrors('amount');
        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/extend", ['expiry_date' => now()->addYear()->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/full-return", ['notes' => 'Réception définitive'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $caution->refresh();
        $this->assertEquals('returned', $caution->status);
        $this->assertEquals('5000.00', $caution->amount_returned);
        $this->assertEquals(5, $caution->histories()->count(), 'created, activated, partial return, extended, full return');

        $this->actingAs($user)->get("/finance/cautions/{$caution->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Caution/Cautions/Show')->where('caution.data.remaining', 0)->where('caution.data.partner.name', $customer->name)->has('caution.data.histories', 5));

        $this->actingAs($user)->delete("/finance/caution-types/{$type->id}")->assertSessionHasErrors('caution_type');
    }

    public function test_forfeit_and_other_partner(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $type = CautionType::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->post('/finance/cautions', [
            'caution_type_id' => $type->id, 'direction' => 'received', 'partner_type' => 'other', 'partner_id' => 99,
            'amount' => 3000, 'issue_date' => now()->toDateString(), 'expiry_date' => now()->addMonth()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $caution = Caution::firstOrFail();
        $this->assertNull($caution->partner_type);
        $this->assertNull($caution->partner_id);

        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/forfeit")->assertSessionHasErrors('status');
        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/activate");
        $this->actingAs($user)->post("/finance/cautions/{$caution->id}/forfeit", ['notes' => 'Retard de livraison'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('forfeited', $caution->fresh()->status);
        $this->assertEquals('3000.00', $caution->fresh()->amount_forfeited);
        $this->actingAs($user)->get('/finance/cautions?status=forfeited')->assertInertia(fn (Assert $page) => $page->has('cautions.data', 1));
    }
}
