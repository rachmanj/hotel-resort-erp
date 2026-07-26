<?php

namespace App\Services;

use App\Enums\FolioItemType;
use App\Enums\SpaAppointmentStatus;
use App\Models\Reservation;
use App\Models\SpaAppointment;
use App\Models\SpaTherapist;
use App\Models\SpaTherapistSchedule;
use App\Models\SpaTreatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SpaService
{
    public function __construct(
        private FolioPostingService $folioPostingService,
    ) {}

    /**
     * @param  array{spa_treatment_id: int, spa_therapist_id: int, scheduled_at: string, guest_id?: int|null, reservation_id?: int|null, charged_to_room?: bool}  $data
     */
    public function bookAppointment(array $data, User $bookedBy): SpaAppointment
    {
        $treatment = SpaTreatment::query()->findOrFail($data['spa_treatment_id']);
        $therapist = SpaTherapist::query()->findOrFail($data['spa_therapist_id']);
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $chargedToRoom = (bool) ($data['charged_to_room'] ?? false);
        $reservationId = $data['reservation_id'] ?? null;
        $guestId = $data['guest_id'] ?? null;

        if ($chargedToRoom && $reservationId === null) {
            throw new InvalidArgumentException('Reservation is required when charging to room.');
        }

        if (! $this->isAvailable($therapist->id, $treatment->id, $scheduledAt)) {
            throw new InvalidArgumentException('Therapist is not available at the requested time.');
        }

        return DB::transaction(function () use ($treatment, $therapist, $scheduledAt, $chargedToRoom, $reservationId, $guestId, $bookedBy): SpaAppointment {
            $appointment = SpaAppointment::query()->create([
                'spa_treatment_id' => $treatment->id,
                'spa_therapist_id' => $therapist->id,
                'guest_id' => $guestId,
                'reservation_id' => $reservationId,
                'scheduled_at' => $scheduledAt,
                'status' => SpaAppointmentStatus::Booked->value,
                'charged_to_room' => $chargedToRoom,
            ]);

            if ($chargedToRoom && $reservationId !== null) {
                $this->chargeToRoom($appointment, $reservationId, $bookedBy);
            }

            return $appointment->fresh(['treatment', 'therapist', 'guest', 'reservation']);
        });
    }

    public function isAvailable(int $therapistId, int $treatmentId, Carbon $scheduledAt): bool
    {
        $treatment = SpaTreatment::query()->findOrFail($treatmentId);
        $therapist = SpaTherapist::query()->findOrFail($therapistId);

        $start = $scheduledAt->copy();
        $end = $start->copy()->addMinutes($treatment->duration_minutes);

        $schedule = SpaTherapistSchedule::query()
            ->where('spa_therapist_id', $therapist->id)
            ->whereDate('work_date', $start->toDateString())
            ->first();

        if ($schedule === null) {
            return false;
        }

        $shiftStart = Carbon::parse($start->toDateString().' '.$schedule->start_time);
        $shiftEnd = Carbon::parse($start->toDateString().' '.$schedule->end_time);

        if ($start->lt($shiftStart) || $end->gt($shiftEnd)) {
            return false;
        }

        $existingAppointments = SpaAppointment::query()
            ->with('treatment:id,duration_minutes')
            ->where('spa_therapist_id', $therapistId)
            ->whereNotIn('status', [
                SpaAppointmentStatus::Cancelled->value,
                SpaAppointmentStatus::NoShow->value,
            ])
            ->whereDate('scheduled_at', $start->toDateString())
            ->get();

        foreach ($existingAppointments as $existing) {
            $existingStart = $existing->scheduled_at;
            $existingEnd = $existingStart->copy()->addMinutes($existing->treatment->duration_minutes);

            if ($start->lt($existingEnd) && $end->gt($existingStart)) {
                return false;
            }
        }

        return true;
    }

    public function updateStatus(SpaAppointment $appointment, string $status): SpaAppointment
    {
        $newStatus = SpaAppointmentStatus::from($status);

        if ($appointment->status === SpaAppointmentStatus::Cancelled) {
            throw new InvalidArgumentException('Cannot update a cancelled appointment.');
        }

        if ($appointment->status === SpaAppointmentStatus::Completed && $newStatus !== SpaAppointmentStatus::Completed) {
            throw new InvalidArgumentException('Cannot change status of a completed appointment.');
        }

        $appointment->update(['status' => $newStatus->value]);

        return $appointment->fresh(['treatment', 'therapist', 'guest', 'reservation']);
    }

    public function cancelAppointment(SpaAppointment $appointment): SpaAppointment
    {
        if ($appointment->status === SpaAppointmentStatus::Completed) {
            throw new InvalidArgumentException('Cannot cancel a completed appointment.');
        }

        $appointment->update(['status' => SpaAppointmentStatus::Cancelled->value]);

        return $appointment;
    }

    public function chargeToRoom(SpaAppointment $appointment, int $reservationId, User $postedBy): SpaAppointment
    {
        if ($appointment->charged_to_room && $appointment->folio_item_id !== null) {
            throw new InvalidArgumentException('Appointment is already charged to room.');
        }

        $appointment->load('treatment');
        $reservation = Reservation::query()->with('guest')->findOrFail($reservationId);

        $folio = $this->folioPostingService->findOrCreateMasterFolio(
            $reservation->hotel_id,
            $reservation->id,
            $reservation->guest_id,
        );

        $description = 'Spa: '.$appointment->treatment->name;

        $folioItem = $this->folioPostingService->postCharge(
            folio: $folio,
            itemType: FolioItemType::Spa->value,
            description: $description,
            amount: (float) $appointment->treatment->price,
            referenceType: SpaAppointment::class,
            referenceId: $appointment->id,
            postedBy: $postedBy,
        );

        $appointment->update([
            'charged_to_room' => true,
            'reservation_id' => $reservationId,
            'folio_item_id' => $folioItem->id,
        ]);

        return $appointment->fresh(['treatment', 'therapist', 'guest', 'reservation', 'folioItem']);
    }
}
