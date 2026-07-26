<?php

namespace App\Services\Reports;

use App\Enums\HousekeepingAssignmentStatus;
use App\Enums\HousekeepingStatus;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HousekeepingEfficiencyReport
{
    /**
     * @return array{
     *     avg_clean_minutes: float|null,
     *     inspection_pass_rate: float|null,
     *     by_housekeeper: Collection<int, array{
     *         housekeeper_id: int,
     *         housekeeper_name: string,
     *         rooms_assigned: int,
     *         rooms_completed: int,
     *         avg_clean_minutes: float|null
     *     }>,
     *     totals: array{rooms_assigned: int, rooms_completed: int, inspections_passed: int, rooms_cleaned: int}
     * }
     */
    public function generate(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $logs = HousekeepingLog::query()
            ->join('rooms', 'housekeeping_logs.room_id', '=', 'rooms.id')
            ->where('rooms.hotel_id', $hotelId)
            ->whereBetween('housekeeping_logs.changed_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->orderBy('housekeeping_logs.room_id')
            ->orderBy('housekeeping_logs.changed_at')
            ->select([
                'housekeeping_logs.id',
                'housekeeping_logs.room_id',
                'housekeeping_logs.status',
                'housekeeping_logs.changed_at',
                'housekeeping_logs.housekeeping_assignment_id',
            ])
            ->get();

        $cleanDurations = $this->calculateCleanDurations($logs);
        $avgCleanMinutes = $cleanDurations->isNotEmpty()
            ? round($cleanDurations->avg(), 2)
            : null;

        $roomsCleaned = $logs->where('status', HousekeepingStatus::Clean->value)->count();
        $inspectionsPassed = $logs->where('status', HousekeepingStatus::Inspected->value)->count();
        $inspectionPassRate = $roomsCleaned > 0
            ? round(($inspectionsPassed / $roomsCleaned) * 100, 2)
            : null;

        $assignments = HousekeepingAssignment::query()
            ->join('rooms', 'housekeeping_assignments.room_id', '=', 'rooms.id')
            ->join('users', 'housekeeping_assignments.housekeeper_id', '=', 'users.id')
            ->where('rooms.hotel_id', $hotelId)
            ->whereBetween('housekeeping_assignments.assignment_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->select([
                'housekeeping_assignments.id',
                'housekeeping_assignments.housekeeper_id',
                'users.name as housekeeper_name',
                'housekeeping_assignments.status',
            ])
            ->get()
            ->groupBy('housekeeper_id');

        $byHousekeeper = $assignments->map(function (Collection $housekeeperAssignments, int $housekeeperId): array {
            $assignmentIds = $housekeeperAssignments->pluck('id');
            $housekeeperDurations = $cleanDurations->filter(function (float $minutes, int $assignmentId) use ($assignmentIds): bool {
                return $assignmentIds->contains($assignmentId);
            });

            $roomsCompleted = $housekeeperAssignments->filter(
                fn ($assignment) => $assignment->status === HousekeepingAssignmentStatus::Done->value
            )->count();

            return [
                'housekeeper_id' => $housekeeperId,
                'housekeeper_name' => $housekeeperAssignments->first()->housekeeper_name,
                'rooms_assigned' => $housekeeperAssignments->count(),
                'rooms_completed' => $roomsCompleted,
                'avg_clean_minutes' => $housekeeperDurations->isNotEmpty()
                    ? round($housekeeperDurations->avg(), 2)
                    : null,
            ];
        })->values();

        return [
            'avg_clean_minutes' => $avgCleanMinutes,
            'inspection_pass_rate' => $inspectionPassRate,
            'by_housekeeper' => $byHousekeeper,
            'totals' => [
                'rooms_assigned' => $assignments->flatten()->count(),
                'rooms_completed' => $byHousekeeper->sum('rooms_completed'),
                'inspections_passed' => $inspectionsPassed,
                'rooms_cleaned' => $roomsCleaned,
            ],
        ];
    }

    /**
     * @return Collection<int, float>
     */
    private function calculateCleanDurations(Collection $logs): Collection
    {
        $durations = collect();
        $grouped = $logs->groupBy('room_id');

        foreach ($grouped as $roomLogs) {
            $sorted = $roomLogs->sortBy('changed_at')->values();
            $startAt = null;
            $assignmentId = null;

            foreach ($sorted as $log) {
                $status = $log->status instanceof HousekeepingStatus
                    ? $log->status
                    : HousekeepingStatus::tryFrom($log->status);

                if ($status === HousekeepingStatus::Dirty || $status === HousekeepingStatus::Cleaning) {
                    $startAt = Carbon::parse($log->changed_at);
                    $assignmentId = $log->housekeeping_assignment_id;
                }

                if ($status === HousekeepingStatus::Clean && $startAt !== null) {
                    $endAt = Carbon::parse($log->changed_at);
                    $minutes = $startAt->diffInMinutes($endAt);

                    if ($minutes > 0 && $assignmentId !== null) {
                        $durations->put((int) $assignmentId, (float) $minutes);
                    }

                    $startAt = null;
                    $assignmentId = null;
                }
            }
        }

        return $durations;
    }
}
