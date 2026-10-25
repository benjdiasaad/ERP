<?php

namespace Database\Seeders;

use App\Models\Auth\User;
use App\Models\Caution\CautionType;
use App\Models\Company\Company;
use App\Models\Event\EventCategory;
use App\Models\Finance\BankAccount;
use App\Models\Finance\ChartOfAccount;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\PaymentTerm;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Personnel\Department;
use App\Models\Personnel\Position;
use App\Models\Sales\Customer;
use App\Services\CRM\ActivityService;
use App\Services\CRM\ContactService;
use App\Services\CRM\LeadService;
use App\Services\CRM\OpportunityService;
use App\Services\Event\EventService;
use App\Services\Personnel\PersonnelService;
use App\Services\Purchasing\SupplierService;
use App\Services\Sales\CustomerService;
use App\Services\Sales\QuoteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Sample data for local development and demos.
 *
 * Expects DatabaseSeeder to have created the test user. Each company is
 * seeded once (skipped when it already has customers), acting as the
 * test user so company scoping, created_by and references behave as in
 * the API.
 */
class DemoSeeder extends Seeder
{
    /** Per-company data that differs, so tenant isolation is visible. */
    private const COMPANIES = [
        'Atlas Trading SARL' => [
            'email'     => 'contact@atlas-trading.ma',
            'customers' => [
                ['name' => 'Marjane Holding', 'city' => 'Casablanca', 'email' => 'achats@marjane.example'],
                ['name' => 'Hôtel Mogador', 'city' => 'Marrakech', 'email' => 'eco@mogador.example'],
                ['name' => 'Clinique Al Amal', 'city' => 'Rabat', 'email' => 'admin@alamal.example'],
                ['name' => 'Société Riad Fès', 'city' => 'Fès', 'email' => 'contact@riadfes.example'],
            ],
            'suppliers' => [
                ['name' => 'Maroc Bureautique', 'city' => 'Casablanca'],
                ['name' => 'Sud Emballages', 'city' => 'Agadir'],
                ['name' => 'TechnoDistrib', 'city' => 'Tanger'],
            ],
            'contacts' => [
                ['first_name' => 'Yasmine', 'last_name' => 'El Fassi', 'job_title' => 'Responsable achats', 'customer' => 0],
                ['first_name' => 'Karim', 'last_name' => 'Bennani', 'job_title' => 'Directeur général', 'customer' => 1],
                ['first_name' => 'Salma', 'last_name' => 'Tazi', 'job_title' => 'Office manager', 'customer' => 2],
                ['first_name' => 'Omar', 'last_name' => 'Alaoui', 'job_title' => 'Gérant', 'organization' => 'Café Central'],
                ['first_name' => 'Nadia', 'last_name' => 'Chraibi', 'job_title' => 'DAF', 'organization' => 'Groupe Atlantique'],
            ],
        ],
        'Sahara Distribution SA' => [
            'email'     => 'contact@sahara-distribution.ma',
            'customers' => [
                ['name' => 'Supermarché Al Wifaq', 'city' => 'Laâyoune', 'email' => 'wifaq@example.ma'],
                ['name' => 'Pharmacie Dakhla', 'city' => 'Dakhla', 'email' => 'pharma.dakhla@example.ma'],
                ['name' => 'BTP Oasis', 'city' => 'Guelmim', 'email' => 'oasis.btp@example.ma'],
            ],
            'suppliers' => [
                ['name' => 'Import Express', 'city' => 'Casablanca'],
                ['name' => 'Logistique du Sud', 'city' => 'Agadir'],
            ],
            'contacts' => [
                ['first_name' => 'Hamza', 'last_name' => 'Ouazzani', 'job_title' => 'Acheteur', 'customer' => 0],
                ['first_name' => 'Imane', 'last_name' => 'Berrada', 'job_title' => 'Pharmacienne', 'customer' => 1],
                ['first_name' => 'Rachid', 'last_name' => 'Squalli', 'job_title' => 'Chef de projet', 'organization' => 'Énergie Verte'],
            ],
        ],
    ];

    private const STAFF = [
        ['first_name' => 'Sara', 'last_name' => 'Manager', 'role' => 'manager'],
        ['first_name' => 'Youssef', 'last_name' => 'Commercial', 'role' => 'user'],
        ['first_name' => 'Leila', 'last_name' => 'Lecteur', 'role' => 'viewer'],
    ];

    public function run(): void
    {
        $owner = User::where('email', DatabaseSeeder::TEST_EMAIL)->first()
            ?? throw new RuntimeException('Run DatabaseSeeder first: the test user does not exist.');

        $defaultCompanyId = $owner->current_company_id;

        foreach (self::COMPANIES as $name => $profile) {
            $company = Company::firstOrCreate(
                ['name' => $name],
                ['legal_name' => $name, 'email' => $profile['email'], 'currency' => 'MAD', 'is_active' => true]
            );

            DatabaseSeeder::attachToCompany($owner, $company, 'super_admin');
            (new CurrencySeeder())->seedForCompany($company);
            (new TaxSeeder())->seedForCompany($company);

            if (Customer::withoutGlobalScopes()->where('company_id', $company->id)->exists()) {
                $this->command?->warn("DemoSeeder: {$name} already has data, skipped.");
                continue;
            }

            $owner->forceFill(['current_company_id' => $company->id])->save();
            Auth::setUser($owner->fresh());

            $staff = $this->seedStaff($company);
            $this->seedCompany($company, $profile, $staff);

            $this->command?->info("DemoSeeder: {$name} seeded.");
        }

        $owner->forceFill(['current_company_id' => $defaultCompanyId])->save();
        Auth::forgetUser();
    }

    /**
     * @return array<int, User>
     */
    private function seedStaff(Company $company): array
    {
        $domain = str($company->name)->before(' ')->lower();

        return array_map(function (array $member) use ($company, $domain): User {
            $email = str("{$member['first_name']}.{$member['role']}@{$domain}.test")->lower()->toString();

            $user = User::where('email', $email)->first() ?? User::factory()->create([
                'name'       => "{$member['first_name']} {$member['last_name']}",
                'first_name' => $member['first_name'],
                'last_name'  => $member['last_name'],
                'email'      => $email,
                'password'   => Hash::make(DatabaseSeeder::TEST_PASSWORD),
            ]);

            DatabaseSeeder::attachToCompany($user, $company, $member['role'], isDefault: true);

            return $user;
        }, self::STAFF);
    }

    /**
     * @param  array<int, User>  $staff
     */
    private function seedCompany(Company $company, array $profile, array $staff): void
    {
        $mad = Currency::where('code', 'MAD')->firstOrFail();

        // ─── Finance ──────────────────────────────────────────────────────────
        $terms = collect([['Comptant', 0], ['30 jours', 30], ['60 jours', 60]])
            ->map(fn ($t) => PaymentTerm::create(['name' => $t[0], 'days' => $t[1], 'is_active' => true]));

        foreach ([['Virement', 'bank_transfer'], ['Chèque', 'check'], ['Espèces', 'cash'], ['Carte bancaire', 'credit_card']] as [$name, $type]) {
            PaymentMethod::create(['name' => $name, 'type' => $type, 'is_active' => true]);
        }

        foreach ([
            ['1111', 'Capital social', 'equity'],
            ['3421', 'Clients', 'asset'],
            ['3455', 'TVA récupérable', 'asset'],
            ['4411', 'Fournisseurs', 'liability'],
            ['4455', 'TVA facturée', 'liability'],
            ['5141', 'Banques', 'asset'],
            ['6111', 'Achats de marchandises', 'expense'],
            ['7111', 'Ventes de marchandises', 'revenue'],
        ] as [$code, $name, $type]) {
            ChartOfAccount::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true, 'balance' => 0]);
        }

        BankAccount::create([
            'name'           => 'Compte principal',
            'bank'           => 'Attijariwafa Bank',
            'account_number' => '0071' . str_pad((string) $company->id, 12, '0', STR_PAD_LEFT),
            'currency_id'    => $mad->id,
            'balance'        => 250000,
        ]);

        // ─── Personnel ────────────────────────────────────────────────────────
        $personnelService = app(PersonnelService::class);
        $departments = [];
        foreach (['Direction' => 'DIR', 'Commercial' => 'COM', 'Comptabilité' => 'CPT', 'Logistique' => 'LOG'] as $name => $code) {
            $department = Department::create(['name' => $name, 'code' => $code, 'is_active' => true]);
            $departments[$code] = [
                'department' => $department,
                'position'   => Position::create([
                    'department_id' => $department->id,
                    'name'          => "Responsable {$name}",
                    'code'          => "RESP-{$code}",
                    'salary_min'    => 12000,
                    'salary_max'    => 25000,
                    'is_active'     => true,
                ]),
            ];
        }

        foreach ($staff as $index => $user) {
            $slot = array_values($departments)[$index % count($departments)];
            $personnelService->create([
                'user_id'         => $user->id,
                'department_id'   => $slot['department']->id,
                'position_id'     => $slot['position']->id,
                'first_name'      => $user->first_name,
                'last_name'       => $user->last_name,
                'email'           => $user->email,
                'gender'          => $index % 2 === 0 ? 'female' : 'male',
                'employment_type' => 'full_time',
                'hire_date'       => now()->subYears($index + 1)->toDateString(),
                'status'          => 'active',
                'city'            => 'Casablanca',
                'country'         => 'MA',
            ]);
        }

        // ─── Inventory ────────────────────────────────────────────────────────
        $categories = collect(['Informatique' => 'INF', 'Mobilier' => 'MOB', 'Fournitures' => 'FOU'])
            ->map(fn ($code, $name) => ProductCategory::create(['name' => $name, 'code' => $code, 'is_active' => true]));

        $warehouses = [
            // The column is is_default, but Warehouse::$fillable lists "default", so set it directly.
            tap(
                Warehouse::create(['code' => 'WH-CASA', 'name' => 'Entrepôt Casablanca', 'address' => 'Zone industrielle Ain Sebaâ']),
                fn (Warehouse $warehouse) => $warehouse->forceFill(['is_default' => true])->save()
            ),
            Warehouse::create(['code' => 'WH-TNG', 'name' => 'Entrepôt Tanger', 'address' => 'Zone franche de Tanger']),
        ];

        $products = [];
        foreach ([
            ['INF-001', 'Ordinateur portable 14"', 'Informatique', 6500, 8900],
            ['INF-002', 'Écran 27" IPS', 'Informatique', 1800, 2600],
            ['INF-003', 'Imprimante laser', 'Informatique', 1200, 1750],
            ['MOB-001', 'Bureau direction', 'Mobilier', 2800, 4200],
            ['MOB-002', 'Chaise ergonomique', 'Mobilier', 900, 1450],
            ['FOU-001', 'Ramette papier A4 (carton)', 'Fournitures', 180, 260],
            ['FOU-002', 'Cartouche toner', 'Fournitures', 350, 520],
        ] as $index => [$code, $name, $category, $purchase, $sale]) {
            $product = Product::create([
                'category_id'     => $categories[$category]->id,
                'code'            => $code,
                'name'            => $name,
                'type'            => 'product',
                'unit'            => 'unit',
                'purchase_price'  => $purchase,
                'sale_price'      => $sale,
                'tax_rate'        => 20,
                'min_stock_level' => 5,
                'reorder_point'   => 10,
                'is_active'       => true,
                'is_purchasable'  => true,
                'is_sellable'     => true,
                'is_stockable'    => true,
            ]);
            $products[] = $product;

            foreach ($warehouses as $w => $warehouse) {
                StockLevel::create([
                    'product_id'        => $product->id,
                    'warehouse_id'      => $warehouse->id,
                    'quantity_on_hand'  => ($index + 1) * ($w === 0 ? 12 : 4),
                    'quantity_reserved' => 0,
                ]);
            }
        }

        $products[] = Product::create([
            'code' => 'SRV-001', 'name' => 'Installation et configuration', 'type' => 'service', 'unit' => 'hour',
            'purchase_price' => 0, 'sale_price' => 450, 'tax_rate' => 20,
            'is_active' => true, 'is_purchasable' => false, 'is_sellable' => true, 'is_stockable' => false,
        ]);

        // ─── Sales & Purchasing ───────────────────────────────────────────────
        $customerService = app(CustomerService::class);
        $customers = array_map(fn (array $c) => $customerService->create([
            'type'             => 'company',
            'name'             => $c['name'],
            'email'            => $c['email'],
            'city'             => $c['city'],
            'country'          => 'MA',
            'payment_terms_id' => $terms[1]->id,
            'credit_limit'     => 100000,
            'currency_id'      => $mad->id,
            'is_active'        => true,
        ]), $profile['customers']);

        $supplierService = app(SupplierService::class);
        foreach ($profile['suppliers'] as $s) {
            $supplierService->create([
                'type'            => 'company',
                'name'            => $s['name'],
                'city'            => $s['city'],
                'country'         => 'MA',
                'payment_term_id' => $terms[2]->id,
                'is_active'       => true,
            ]);
        }

        $quoteService = app(QuoteService::class);
        foreach ($customers as $index => $customer) {
            $quote = $quoteService->create([
                'customer_id'     => $customer->id,
                'quote_date'      => now()->subDays(10 - $index)->toDateString(),
                'validity_date'   => now()->addDays(20 + $index)->toDateString(),
                'currency_id'     => $mad->id,
                'payment_term_id' => $terms[1]->id,
                'lines'           => array_map(fn (Product $product) => [
                    'product_id'     => $product->id,
                    'description'    => $product->name,
                    'quantity'       => 2 + $index,
                    'unit_price_ht'  => $product->sale_price,
                    'discount_type'  => 'percentage',
                    'discount_value' => $index === 0 ? 5 : 0,
                    'tax_rate'       => 20,
                ], array_slice($products, $index, 2)),
            ]);

            // Spread quotes across statuses: draft, sent, accepted, …
            if ($index % 3 >= 1) {
                $quote = $quoteService->send($quote);
            }
            if ($index % 3 === 2) {
                $quoteService->accept($quote);
            }
        }

        // ─── Caution & Events ─────────────────────────────────────────────────
        CautionType::create(['name' => 'Caution provisoire', 'description' => "Garantie de soumission aux appels d'offres", 'default_percentage' => 1.5]);
        CautionType::create(['name' => 'Caution définitive', 'description' => 'Garantie de bonne exécution', 'default_percentage' => 3]);

        $meetings = EventCategory::create(['name' => 'Réunions', 'color' => '#2563eb']);
        $trainings = EventCategory::create(['name' => 'Formations', 'color' => '#16a34a']);

        $eventService = app(EventService::class);
        foreach ([
            [$meetings, 'Réunion commerciale hebdomadaire', 'meeting', 2, 1],
            [$trainings, 'Formation sécurité incendie', 'training', 9, 4],
            [$meetings, 'Revue budgétaire trimestrielle', 'meeting', 21, 3],
        ] as [$category, $title, $type, $inDays, $hours]) {
            $start = now()->addDays($inDays)->setTime(10, 0);
            $eventService->create([
                'event_category_id' => $category->id,
                'title'             => $title,
                'type'              => $type,
                'location'          => 'Siège social',
                'start_date'        => $start,
                'end_date'          => $start->copy()->addHours($hours),
                'is_mandatory'      => $type === 'training',
            ]);
        }

        $this->seedCrm($customers, $profile['contacts'], $staff);
    }

    /**
     * @param  array<int, Customer>  $customers
     * @param  array<int, User>  $staff
     */
    private function seedCrm(array $customers, array $contactRows, array $staff): void
    {
        $contactService = app(ContactService::class);
        $leadService = app(LeadService::class);
        $opportunityService = app(OpportunityService::class);
        $activityService = app(ActivityService::class);
        $salesRep = $staff[1];

        $contacts = array_map(fn (array $row) => $contactService->create([
            'first_name'   => $row['first_name'],
            'last_name'    => $row['last_name'],
            'job_title'    => $row['job_title'],
            'organization' => $row['organization'] ?? (isset($row['customer']) ? $customers[$row['customer']]->name : null),
            'customer_id'  => isset($row['customer']) ? $customers[$row['customer']]->id : null,
            'email'        => str("{$row['first_name']}.{$row['last_name']}@example.ma")->lower()->replace(' ', '')->toString(),
            'source'       => 'referral',
            'country'      => 'MA',
            'is_active'    => true,
        ]), $contactRows);

        // Leads at different points of the funnel.
        $leadRows = [
            ['Renouvellement parc informatique', 'website', 120000, 'new'],
            ['Aménagement nouveaux bureaux', 'trade_show', 85000, 'contacted'],
            ['Contrat fournitures annuel', 'referral', 36000, 'qualified'],
            ['Équipement salle de formation', 'cold_call', 54000, 'convert'],
            ['Projet borne interactive', 'social_media', 15000, 'lost'],
        ];

        foreach ($leadRows as $index => [$title, $source, $value, $target]) {
            $lead = $leadService->create([
                'title'               => $title,
                'contact_id'          => $contacts[$index % count($contacts)]->id,
                'source'              => $source,
                'estimated_value'     => $value,
                'probability'         => 20,
                'assigned_to'         => $salesRep->id,
                'expected_close_date' => now()->addMonths(1 + $index % 3)->toDateString(),
                'status'              => $target === 'contacted' ? 'contacted' : 'new',
            ]);

            match ($target) {
                'qualified' => $leadService->qualify($lead),
                'convert'   => $leadService->convert($leadService->qualify($lead)),
                'lost'      => $leadService->update($lead, ['status' => 'lost', 'lost_reason' => 'Budget reporté à l\'année prochaine']),
                default     => null,
            };
        }

        // Opportunities spread over the pipeline.
        foreach ([
            ['Déploiement ERP filiale', 240000, 'proposal', 0],
            ['Maintenance annuelle imprimantes', 18000, 'negotiation', 1],
            ['Mobilier open space', 95000, 'qualification', 2],
            ['Fourniture écrans 27"', 52000, 'won', 0],
            ['Installation réseau entrepôt', 30000, 'lost', 1],
        ] as $index => [$title, $value, $stage, $customerIndex]) {
            $customer = $customers[$customerIndex % count($customers)];
            $opportunity = $opportunityService->create([
                'title'               => $title,
                'customer_id'         => $customer->id,
                'contact_id'          => $contacts[$customerIndex % count($contacts)]->id,
                'value'               => $value,
                'assigned_to'         => $salesRep->id,
                'expected_close_date' => now()->addWeeks(2 + $index * 2)->toDateString(),
                'stage'               => in_array($stage, ['won', 'lost'], true) ? 'negotiation' : $stage,
            ]);

            match ($stage) {
                'won'   => $opportunityService->win($opportunity),
                'lost'  => $opportunityService->lose($opportunity, 'Offre concurrente moins chère'),
                default => null,
            };
        }

        // Activities: one overdue, a few upcoming, one done.
        foreach ([
            ['call', 'Relance devis', -2, 'pending', 'contact', $contacts[0]->id],
            ['meeting', 'Présentation de la solution', 3, 'pending', 'contact', $contacts[1]->id],
            ['email', 'Envoi de la documentation technique', 1, 'in_progress', 'customer', $customers[0]->id],
            ['follow_up', 'Point mensuel compte client', 10, 'pending', 'customer', $customers[1]->id],
            ['note', 'Compte rendu premier rendez-vous', -5, 'completed', 'contact', $contacts[2]->id],
        ] as [$type, $subject, $dueInDays, $status, $relatedType, $relatedId]) {
            $activityService->create([
                'type'         => $type,
                'subject'      => $subject,
                'related_type' => $relatedType,
                'related_id'   => $relatedId,
                'assigned_to'  => $salesRep->id,
                'due_date'     => now()->addDays($dueInDays)->setTime(9, 30),
                'priority'     => $dueInDays < 0 ? 'high' : 'medium',
                'status'       => $status,
                'outcome'      => $status === 'completed' ? 'Client intéressé, devis à préparer.' : null,
            ]);
        }
    }
}
