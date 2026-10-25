<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\CRM\Opportunity;
use App\Services\CRM\OpportunityService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpportunityServiceTest extends TestCase
{
    private OpportunityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ['user' => $user] = $this->setUpCompanyAndUser();
        $this->actingAs($user);
        $this->service = app(OpportunityService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Weighted value ───────────────────────────────────────────────────────

    public function test_weighted_value_is_value_times_probability(): void
    {
        $this->assertSame(2500.0, Opportunity::calculateWeightedValue(10000, 25));
        $this->assertSame(0.0, Opportunity::calculateWeightedValue(10000, 0));
        $this->assertSame(330.0, Opportunity::calculateWeightedValue(999.99, 33)); // 329.9967 rounded
    }

    public function test_weighted_value_clamps_probability(): void
    {
        $this->assertSame(1000.0, Opportunity::calculateWeightedValue(1000, 150));
        $this->assertSame(0.0, Opportunity::calculateWeightedValue(1000, -10));
    }

    public function test_weighted_value_is_kept_in_sync_on_save(): void
    {
        $opportunity = $this->service->create(['title' => 'A', 'value' => 8000]);
        $this->assertEquals('800.00', $opportunity->weighted_value);

        $opportunity->update(['probability' => 60]);
        $this->assertEquals('4800.00', $opportunity->fresh()->weighted_value);
    }

    // ─── Stage transitions ────────────────────────────────────────────────────

    public function test_advance_walks_through_open_stages_in_order(): void
    {
        $opportunity = $this->service->create(['title' => 'A', 'value' => 100]);

        foreach (['qualification', 'proposal', 'negotiation'] as $expected) {
            $opportunity = $this->service->advance($opportunity);
            $this->assertSame($expected, $opportunity->stage);
            $this->assertSame(Opportunity::STAGE_PROBABILITIES[$expected], $opportunity->probability);
        }
    }

    public function test_advance_keeps_higher_manual_probability(): void
    {
        $opportunity = $this->service->create(['title' => 'A', 'value' => 100, 'probability' => 60]);

        $opportunity = $this->service->advance($opportunity);

        $this->assertSame('qualification', $opportunity->stage);
        $this->assertSame(60, $opportunity->probability);
    }

    public function test_advance_rejects_same_or_earlier_stage(): void
    {
        $opportunity = $this->service->create(['title' => 'A', 'stage' => 'proposal']);

        $this->expectException(ValidationException::class);
        $this->service->advance($opportunity, 'proposal');
    }

    public function test_advance_rejects_closed_stage_target(): void
    {
        $opportunity = $this->service->create(['title' => 'A']);

        $this->expectException(ValidationException::class);
        $this->service->advance($opportunity, 'closed_won');
    }

    public function test_win_and_lose_set_terminal_probabilities(): void
    {
        $won = $this->service->win($this->service->create(['title' => 'W', 'value' => 500]));
        $lost = $this->service->lose($this->service->create(['title' => 'L', 'value' => 500]), 'Too expensive');

        $this->assertSame('closed_won', $won->stage);
        $this->assertSame(100, $won->probability);
        $this->assertEquals('500.00', $won->weighted_value);

        $this->assertSame('closed_lost', $lost->stage);
        $this->assertSame(0, $lost->probability);
        $this->assertEquals('0.00', $lost->weighted_value);
    }

    public function test_closed_opportunity_cannot_transition(): void
    {
        $won = $this->service->win($this->service->create(['title' => 'W']));

        $this->expectException(ValidationException::class);
        $this->service->advance($won);
    }

    // ─── Forecast ─────────────────────────────────────────────────────────────

    public function test_forecast_buckets_open_and_won_by_month(): void
    {
        Carbon::setTestNow('2026-01-10');

        $this->service->create(['title' => 'Jan', 'value' => 1000, 'probability' => 50, 'expected_close_date' => '2026-01-25']);
        $this->service->create(['title' => 'Feb', 'value' => 2000, 'probability' => 25, 'expected_close_date' => '2026-02-03']);
        $this->service->create(['title' => 'Late', 'value' => 3000, 'probability' => 10, 'expected_close_date' => '2025-12-01']);
        $this->service->create(['title' => 'NoDate', 'value' => 9999]);
        $this->service->win($this->service->create(['title' => 'Won', 'value' => 700]));

        $forecast = $this->service->forecast(2);

        $this->assertSame('2026-01-01', $forecast['from']);
        $this->assertSame('2026-02-28', $forecast['to']);
        $this->assertCount(2, $forecast['periods']);

        [$jan, $feb] = $forecast['periods'];
        $this->assertSame(2, $jan['open_count']);           // Jan + overdue Late
        $this->assertSame(4000.0, $jan['pipeline_value']);
        $this->assertSame(800.0, $jan['weighted_value']);   // 500 + 300
        $this->assertSame(1, $jan['won_count']);
        $this->assertSame(700.0, $jan['won_value']);
        $this->assertSame(500.0, $feb['weighted_value']);
        $this->assertSame(1300.0, $forecast['total_weighted_value']);
    }

    public function test_forecast_clamps_month_range(): void
    {
        $this->assertCount(1, $this->service->forecast(0)['periods']);
        $this->assertCount(24, $this->service->forecast(100)['periods']);
    }
}
