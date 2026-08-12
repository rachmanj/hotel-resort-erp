<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Folio;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    private static ?int $actingUserId = null;

    public static function setActingUser(?int $userId): void
    {
        self::$actingUserId = $userId;
    }

    public static function clearActingUser(): void
    {
        self::$actingUserId = null;
    }

    public static function logCustom(Model $model, string $event, string $description, ?int $userId = null): void
    {
        $observer = new self;
        $resolvedUserId = $userId ?? self::resolveUserId($model);

        ActivityLog::query()->create([
            'hotel_id' => $observer->resolveHotelId($model),
            'user_id' => $resolvedUserId,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'event' => $event,
            'properties' => array_filter([
                'description' => $description,
                'performed_by' => $resolvedUserId,
            ]),
        ]);
    }

    public function created(Model $model): void
    {
        $this->log($model, 'created', [
            'attributes' => $model->getAttributes(),
        ]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $properties = [
            'changes' => $changes,
            'original' => collect($model->getOriginal())->only(array_keys($changes))->all(),
        ];

        if ($model instanceof Reservation
            && isset($changes['status'])
            && $changes['status'] === ReservationStatus::Cancelled->value) {
            $properties['cancellation_reason'] = $model->cancelled_reason;
        }

        $this->log($model, 'updated', $properties);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted', [
            'attributes' => $model->getAttributes(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Model $model, string $event, array $properties): void
    {
        $userId = self::resolveUserId($model);

        if ($userId !== null) {
            $properties['performed_by'] = $userId;
        }

        ActivityLog::query()->create([
            'hotel_id' => $this->resolveHotelId($model),
            'user_id' => $userId,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    private static function resolveUserId(Model $model): ?int
    {
        $authId = auth()->id();

        if ($authId !== null) {
            return (int) $authId;
        }

        if (self::$actingUserId !== null) {
            return self::$actingUserId;
        }

        foreach (['created_by', 'received_by', 'requested_by', 'approved_by', 'reported_by', 'assigned_to'] as $attribute) {
            if (isset($model->{$attribute}) && $model->{$attribute} !== null) {
                return (int) $model->{$attribute};
            }
        }

        return null;
    }

    private function resolveHotelId(Model $model): ?int
    {
        if (isset($model->hotel_id)) {
            return (int) $model->hotel_id;
        }

        if ($model instanceof Payment) {
            $model->loadMissing('folio');

            return $model->folio?->hotel_id;
        }

        if ($model instanceof Folio) {
            return $model->hotel_id;
        }

        if ($model instanceof WorkOrder) {
            $model->loadMissing('maintenanceRequest');

            return $model->maintenanceRequest?->hotel_id;
        }

        if ($model instanceof MaintenanceRequest) {
            return $model->hotel_id;
        }

        return session('current_hotel_id');
    }
}
