<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Sales\Customer;
use App\Models\Sales\Quote;
use App\Services\Sales\QuoteService;
use Tests\TestCase;

class QuoteCalculationTest extends TestCase
{
    private QuoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuoteService();
    }

    // ─── calculateLineAmounts: basic ──────────────────────────────────────────

    public function test_line_subtotal_ht_is_quantity_times_unit_price(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 5,
            'unit_price_ht' => 200.00,
            'discount_type' => null,
            'discount_value' => 0,
            'tax_rate' => 0,
        ]);

        $this->assertEquals(1000.00, $result['subtotal_ht']);
    }

    public function test_line_with_no_discount_has_zero_discount_amount(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 3,
            'unit_price_ht' => 100.00,
            'discount_type' => null,
            'discount_value' => 0,
            'tax_rate' => 0,
        ]);

        $this->assertEquals(0.00, $result['discount_amount']);
        $this->assertEquals(300.00, $result['subtotal_ht_after_discount']);
    }

    // ─── calculateLineAmounts: percentage discount ────────────────────────────

    public function test_percentage_discount_is_applied_to_subtotal_ht(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 10,
            'unit_price_ht' => 100.00,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 0,
        ]);

        // subtotal_ht = 1000, discount = 10% of 1000 = 100
        $this->assertEquals(1000.00, $result['subtotal_ht']);
        $this->assertEquals(100.00, $result['discount_amount']);
        $this->assertEquals(900.00, $result['subtotal_ht_after_discount']);
    }

    public function test_percentage_discount_of_zero_has_no_effect(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 5,
            'unit_price_ht' => 200.00,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'tax_rate' => 0,
        ]);

        $this->assertEquals(0.00, $result['discount_amount']);
        $this->assertEquals(1000.00, $result['subtotal_ht_after_discount']);
    }

    public function test_percentage_discount_of_100_results_in_zero_after_discount(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 2,
            'unit_price_ht' => 500.00,
            'discount_type' => 'percentage',
            'discount_value' => 100,
            'tax_rate' => 0,
        ]);

        $this->assertEquals(1000.00, $result['discount_amount']);
        $this->assertEquals(0.00, $result['subtotal_ht_after_discount']);
        $this->assertEquals(0.00, $result['total_ttc']);
    }

    // ─── calculateLineAmounts: fixed discount ─────────────────────────────────

    public function test_fixed_discount_is_subtracted_from_subtotal_ht(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 4,
            'unit_price_ht' => 250.00,
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
            'tax_rate' => 0,
        ]);

        // subtotal_ht = 1000, fixed discount = 50
        $this->assertEquals(1000.00, $result['subtotal_ht']);
        $this->assertEquals(50.00, $result['discount_amount']);
        $this->assertEquals(950.00, $result['subtotal_ht_after_discount']);
    }

    public function test_fixed_discount_of_zero_has_no_effect(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 3,
            'unit_price_ht' => 100.00,
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'tax_rate' => 0,
        ]);

        $this->assertEquals(0.00, $result['discount_amount']);
        $this->assertEquals(300.00, $result['subtotal_ht_after_discount']);
    }

    // ─── calculateLineAmounts: tax ────────────────────────────────────────────

    public function test_tax_is_applied_to_subtotal_after_discount(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 1,
            'unit_price_ht' => 1000.00,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 20,
        ]);

        // subtotal_ht = 1000, discount = 100, after_discount = 900, tax = 180
        $this->assertEquals(900.00, $result['subtotal_ht_after_discount']);
        $this->assertEquals(180.00, $result['tax_amount']);
        $this->assertEquals(1080.00, $result['total_ttc']);
    }

    public function test_tax_of_zero_results_in_ttc_equal_to_subtotal_after_discount(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 2,
            'unit_price_ht' => 500.00,
            'discount_type' => null,
            'discount_value' => 0,
            'tax_rate' => 0,
        ]);

        $this->assertEquals(0.00, $result['tax_amount']);
        $this->assertEquals(1000.00, $result['total_ttc']);
    }

    public function test_tax_rate_14_percent_is_calculated_correctly(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 1,
            'unit_price_ht' => 1000.00,
            'discount_type' => null,
            'discount_value' => 0,
            'tax_rate' => 14,
        ]);

        $this->assertEquals(140.00, $result['tax_amount']);
        $this->assertEquals(1140.00, $result['total_ttc']);
    }

    public function test_tax_rate_7_percent_is_calculated_correctly(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 1,
            'unit_price_ht' => 1000.00,
            'discount_type' => null,
            'discount_value' => 0,
            'tax_rate' => 7,
        ]);

        $this->assertEquals(70.00, $result['tax_amount']);
        $this->assertEquals(1070.00, $result['total_ttc']);
    }

    // ─── calculateLineAmounts: combined ───────────────────────────────────────

    public function test_full_line_calculation_with_percentage_discount_and_tax(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 5,
            'unit_price_ht' => 200.00,
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'tax_rate' => 20,
        ]);

        // subtotal_ht = 1000
        // discount = 20% of 1000 = 200
        // after_discount = 800
        // tax = 20% of 800 = 160
        // ttc = 960
        $this->assertEquals(1000.00, $result['subtotal_ht']);
        $this->assertEquals(200.00, $result['discount_amount']);
        $this->assertEquals(800.00, $result['subtotal_ht_after_discount']);
        $this->assertEquals(160.00, $result['tax_amount']);
        $this->assertEquals(960.00, $result['total_ttc']);
    }

    public function test_full_line_calculation_with_fixed_discount_and_tax(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 3,
            'unit_price_ht' => 100.00,
            'discount_type' => 'fixed',
            'discount_value' => 30.00,
            'tax_rate' => 20,
        ]);

        // subtotal_ht = 300
        // discount = 30 (fixed)
        // after_discount = 270
        // tax = 20% of 270 = 54
        // ttc = 324
        $this->assertEquals(300.00, $result['subtotal_ht']);
        $this->assertEquals(30.00, $result['discount_amount']);
        $this->assertEquals(270.00, $result['subtotal_ht_after_discount']);
        $this->assertEquals(54.00, $result['tax_amount']);
        $this->assertEquals(324.00, $result['total_ttc']);
    }

    public function test_line_calculation_preserves_original_data(): void
    {
        $input = [
            'description'    => 'Test product',
            'quantity' => 2,
            'unit_price_ht' => 100.00,
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'tax_rate' => 20,
        ];

        $result = $this->service->calculateLineAmounts($input);

        $this->assertEquals('Test product', $result['description']);
        $this->assertEquals(2, $result['quantity']);
        $this->assertEquals(100.00, $result['unit_price_ht']);
    }

    // ─── calculateTotals ──────────────────────────────────────────────────────

    public function test_calculate_totals_sums_all_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        // Line 1: qty=2, price=500, no discount, no tax → subtotal=1000, tax=0, ttc=1000
        $quote->lines()->create([
            'company_id' => $company->id,
            'description' => 'Line 1',
            'quantity' => 2,
            'unit_price_ht' => 500.00,
            'discount_type' => null,
            'discount_value' => 0,
            'subtotal_ht' => 1000.00,
            'discount_amount' => 0.00,
            'subtotal_ht_after_discount' => 1000.00,
            'tax_rate' => 0,
            'tax_amount' => 0.00,
            'total_ttc' => 1000.00,
            'sort_order' => 0,
        ]);

        // Line 2: qty=1, price=200, 10% discount, 20% tax → subtotal=200, discount=20, after=180, tax=36, ttc=216
        $quote->lines()->create([
            'company_id' => $company->id,
            'description' => 'Line 2',
            'quantity' =>  => 1,
            'unit_price_ht' => 200.00,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'subtotal_ht' => 200.00,
            'discount_amount' => 20.00,
            'subtotal_ht_after_discount' => 180.00,
            'tax_rate' =>  => 20,
            'tax_amount' => 36.00,
            'total_ttc' => => 216.00,
            'sort_order' => 1,
        ]);

        $this->service->calculateTotals($quote);
        $quote->refresh();

        // subtotal_ht = 1000 + 200 = 1200
        // total_discount = 0 + 20 = 20
        // total_tax = 0 + 36 = 36
        // total_ttc = 1200 - 20 + 36 = 1216
        $this->assertEquals('1200.00', $quote->subtotal_ht);
        $this->assertEquals('20.00', $quote->total_discount);
        $this->assertEquals('36.00', $quote->total_tax);
        $this->assertEquals('1216.00', $quote->total_ttc);
    }

    public function test_calculate_totals_with_no_lines_results_in_zeros(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->service->calculateTotals($quote);
        $quote->refresh();

        $this->assertEquals('0.00', $quote->subtotal_ht);
        $this->assertEquals('0.00', $quote->total_discount);
        $this->assertEquals('0.00', $quote->total_tax);
        $this->assertEquals('0.00', $quote->total_ttc);
    }

    public function test_calculate_totals_with_single_line_no_discount_no_tax(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $quote->lines()->create([
            'company_id' => $company->id,
            'description' => 'Simple line',
            'quantity' =>  => 3,
            'unit_price_ht' => 100.00,
            'discount_type' => null,
            'discount_value' => 0,
            'subtotal_ht' => 300.00,
            'discount_amount' => 0.00,
            'subtotal_ht_after_discount' => 300.00,
            'tax_rate' => 0,
            'tax_amount' => 0.00,
            'total_ttc' => => 300.00,
            'sort_order' => 0,
        ]);

        $this->service->calculateTotals($quote);
        $quote->refresh();

        $this->assertEquals('300.00', $quote->subtotal_ht);
        $this->assertEquals('0.00', $quote->total_discount);
        $this->assertEquals('0.00', $quote->total_tax);
        $this->assertEquals('300.00', $quote->total_ttc);
    }

    // ─── Rounding ─────────────────────────────────────────────────────────────

    public function test_line_amounts_are_rounded_to_two_decimal_places(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 3,
            'unit_price_ht' => 33.33,
            'discount_type' => null,
            'discount_value' => 0,
            'tax_rate' => 20,
        ]);

        // subtotal_ht = 3 * 33.33 = 99.99
        // tax = 20% of 99.99 = 19.998 → rounded to 20.00
        // ttc = 99.99 + 20.00 = 119.99
        $this->assertEquals(99.99, $result['subtotal_ht']);
        $this->assertEquals(20.00, $result['tax_amount']);
        $this->assertEquals(119.99, $result['total_ttc']);
    }

    public function test_percentage_discount_rounding(): void
    {
        $result = $this->service->calculateLineAmounts([
            'quantity' => 1,
            'unit_price_ht' => 100.00,
            'discount_type' => 'percentage',
            'discount_value' => 33.33,
            'tax_rate' => 0,
        ]);

        // discount = 100 * 33.33 / 100 = 33.33
        $this->assertEquals(33.33, $result['discount_amount']);
        $this->assertEquals(66.67, $result['subtotal_ht_after_discount']);
    }
}
