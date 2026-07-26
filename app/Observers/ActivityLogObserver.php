<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Folio;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
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

        $this->log($model, 'updated', [
            'changes' => $changes,
            'original' => collect($model->getOriginal())->only(array_keys($changes))->all(),
        ]);
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
        ActivityLog::query()->create([
            'hotel_id' => $this->resolveHotelId($model),
            'user_id' => auth()->id(),
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'event' => $event,
            'properties' => $properties,
        ]);
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

        return session('current_hotel_id');
    }
}
