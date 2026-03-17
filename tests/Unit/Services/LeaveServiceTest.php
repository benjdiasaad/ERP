<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Personnel\Leave;
use App\Models\Personnel\Personnel;
use App\Services\Personnel\LeaveService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveServiceTest extends TestCase
{
    private LeaveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeaveService();
    }

    // ─── Balance calculation ──────────────────────────────────────────────────

    public function test_balance_returns_full_allocation_when_no_leaves_taken(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $balance = $this->service->getBalance($personnel->id, 'annual', now()->year);

        $this->assertSame(22.0, $balance);
    }

    public function test_balance_deducted_after_approved_leave(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'leave_type'   => 'annual',
            'start_date'   => now()->startOfYear()->toDateString(),
            'end_date'     => now()->startOfYear()->addDays(4)->toDateString(),
            'total_days'   => 5,
            'status'       => 'approved',
        ]);

        $balance = $this->service->getBalance($personnel->id, 'annual', now()->year);

        $this->assertSame(17.0, $balance);
    }

    public function test_balance_not_affected_by_pending_leave(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'leave_type'   => 'annual',
            'start_date'   => now()->startOfYear()->toDateString(),
            'end_date'     => now()->startOfYear()->addDays(4)->toDateString(),
            'total_days'   => 5,
            'status'       => 'pending',
        ]);

        $balance = $this->service->getBalance($personnel->id, 'annual', now()->year);

        $this->assertSame(22.0, $balance);
    }

    public function test_balance_not_affected_by_rejected_leave(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'leave_type'   => 'annual',
            'start_date'   => now()->startOfYear()->toDateString(),
            'end_date'     => now()->startOfYear()->addDays(4)->toDateString(),
            'total_days'   => 5,
            'status'       => 'rejected',
        ]);

        $balance = $this->service->getBalance($personnel->id, 'annual', now()->year);

        $this->assertSame(22.0, $balance);
    }

    public function test_balance_never_goes_below_zero(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        // Approve more days than allocated (edge case)
        Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'leave_type'   => 'annual',
            'start_date'   => now()->startOfYear()->toDateString(),
            'end_date'     => now()->startOfYear()->addDays(29)->toDateString(),
            'total_days'   => 30,
            'status'       => 'approved',
        ]);

        $balance = $this->service->getBalance($personnel->id, 'annual', now()->year);

        $this->assertSame(0.0, $balance);
    }

    public function test_balance_only_counts_leaves_for_given_year(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        // Leave from previous year — should not affect current year balance
        Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'leave_type'   => 'annual',
            'start_date'   => now()->subYear()->startOfYear()->toDateString(),
            'end_date'     => now()->subYear()->startOfYear()->addDays(4)->toDateString(),
            'total_days'   => 5,
            'status'       => 'approved',
        ]);

        $balance = $this->service->getBalance($personnel->id, 'annual', now()->year);

        $this->assertSame(22.0, $balance);
    }

    public function test_sick_leave_allocation_is_15_days(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $balance = $this->service->getBalance($personnel->id, 'sick', now()->year);

        $this->assertSame(15.0, $balance);
    }

    public function test_maternity_leave_allocation_is_98_days(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $balance = $this->service->getBalance($personnel->id, 'maternity', now()->year);

        $this->assertSame(98.0, $balance);
    }

    public function test_paternity_leave_allocation_is_3_days(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $balance = $this->service->getBalance($personnel->id, 'paternity', now()->year);

        $this->assertSame(3.0, $balance);
    }

    public function test_unknown_leave_type_has_zero_allocation(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $balance = $this->service->getBalance($personnel->id, 'unknown_type', now()->year);

        $this->assertSame(0.0, $balance);
    }

    // ─── Approval workflow ────────────────────────────────────────────────────

    public function test_approve_sets_status_to_approved(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'status'       => 'pending',
        ]);

        $result = $this->service->approve($leave, $user->id);

        $this->assertSame('approved', $result->status);
        $this->assertSame($user->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_reject_sets_status_to_rejected_with_reason(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'status'       => 'pending',
        ]);

        $result = $this->service->reject($leave, $user->id, 'Insufficient balance');

        $this->assertSame('rejected', $result->status);
        $this->assertSame('Insufficient balance', $result->rejection_reason);
    }

    public function test_cancel_sets_status_to_cancelled_when_pending(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'status'       => 'pending',
        ]);

        $result = $this->service->cancel($leave);

        $this->assertSame('cancelled', $result->status);
    }

    public function test_cancel_throws_when_leave_is_already_approved(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'status'       => 'approved',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->cancel($leave);
    }

    public function test_cancel_throws_when_leave_is_already_rejected(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'status'       => 'rejected',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->cancel($leave);
    }

    // ─── Balance deduction / restoration ─────────────────────────────────────

    public function test_balance_deducted_on_approval(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $year = now()->year;

        $leave = Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'leave_type'   => 'annual',
            'start_date'   => now()->startOfYear()->toDateString(),
            'end_date'     => now()->startOfYear()->addDays(4)->toDateString(),
            'total_days'   => 5,
            'status'       => 'pending',
        ]);

        $balanceBefore = $this->service->getBalance($personnel->id, 'annual', $year);
        $this->service->approve($leave, $user->id);
        $balanceAfter = $this->service->getBalance($personnel->id, 'annual', $year);

        $this->assertSame($balanceBefore - 5.0, $balanceAfter);
    }

    public function test_balance_restored_after_rejection(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $year = now()->year;

        // Create an approved leave first
        $leave = Leave::factory()->create([
            'company_id'   => $company->id,
            'personnel_id' => $personnel->id,
            'leave_type'   => 'annual',
            'start_date'   => now()->startOfYear()->toDateString(),
            'end_date'     => now()->startOfYear()->addDays(4)->toDateString(),
            'total_days'   => 5,
            'status'       => 'approved',
        ]);

        $balanceAfterApproval = $this->service->getBalance($personnel->id, 'annual', $year);

        // Now reject it — balance should be restored
        $this->service->reject($leave, $user->id, 'Changed decision');
        $balanceAfterRejection = $this->service->getBalance($personnel->id, 'annual', $year);

        $this->assertSame($balanceAfterApproval + 5.0, $balanceAfterRejection);
    }

    // ─── Pending approvals ────────────────────────────────────────────────────

    public function test_get_pending_approvals_returns_only_pending_leaves(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'pending']);
        Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'approved']);
        Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'rejected']);

        $pending = $this->service->getPendingApprovals();

        $this->assertCount(1, $pending);
        $this->assertSame('pending', $pending->first()->status);
    }
}
