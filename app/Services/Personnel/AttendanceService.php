<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Attendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AttendanceService
{
    /** Standard working hours per day used for overtime calculation. */
    private const STANDARD_HOURS = 8.0;

    /**
     * Create a new attendance record.
     */
    public function create(array $data): Attendance
    {
        $data['created_by'] = auth()->id();

        return Attendance::create($data);
    }

    /**
     * Update an existing attendance record.
     */
    public function update(Attendance $attendance, array $data): Attendance
    {
        $attendance->update($data);

        return $attendance->fresh();
    }

    /**
     * Record check-in for a personnel member.
     *
     * Creates today's attendance record if it does not exist yet,
     * or updates the check_in time on an existing one.
     */
    public function checkIn(int $personnelId, string $datetime): Attendance
    {
        $date = Carbon::parse($datetime)->toDateString();

        $attendance = Attendance::firstOrNew([
            'personnel_id' => $personnelId,
            'date'         => $date,
        ]);

        $attendance->check_in  = $datetime;
        $attendance->status    = $attendance->status ?? 'present';
        $attendance->created_by = $attendance->created_by ?? auth()->id();
        $attendance->save();

        return $attendance->fresh();
    }

    /**
     * Record check-out for a personnel member and calculate total hours.
     */
    public function checkOut(int $personnelId, string $datetime): Attendance
    {
        $date = Carbon::parse($datetime)->toDateString();

        /** @var Attendance $attendance */
        $attendance = Attendance::where('personnel_id', $personnelId)
            ->where('date', $date)
            ->firstOrFail();

        $attendance->check_out = $datetime;

        $totalHours = $this->calculateHours($attendance->fill(['check_out' => $datetime]));

        $attendance->total_hours    = $totalHours;
        $attendance->overtime_hours = max(0.0, $totalHours - self::STANDARD_HOURS);
        $attendance->save();

        return $attendance->fresh();
    }

    /**
     * Compute the number of hours between check_in and check_out.
     */
    public function calculateHours(Attendance $attendance): float
    {
        if (!$attendance->check_in || !$attendance->check_out) {
            return 0.0;
        }

        $in  = Carbon::parse($attendance->check_in);
        $out = Carbon::parse($attendance->check_out);

        return round($in->floatDiffInHours($out), 2);
    }

    /**
     * Get all attendance records for a personnel member in a given month.
     */
    public function getMonthlyReport(int $personnelId, int $year, int $month): Collection
    {
        return Attendance::where('personnel_id', $personnelId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();
    }

    /**
     * Get total overtime hours for a personnel member in a given month.
     *
     * Returns an array with total_hours, overtime_hours and working_days.
     */
    public function getOvertimeReport(int $personnelId, int $year, int $month): array
    {
        $records = $this->getMonthlyReport($personnelId, $year, $month);

        return [
            'total_hours'    => round((float) $records->sum('total_hours'), 2),
            'overtime_hours' => round((float) $records->sum('overtime_hours'), 2),
            'working_days'   => $records->whereNotIn('status', ['absent', 'holiday'])->count(),
        ];
    }
}
