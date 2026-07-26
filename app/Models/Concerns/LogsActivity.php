<?php

namespace App\Models\Concerns;

use App\Observers\ActivityLogObserver;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        $observer = new ActivityLogObserver;

        static::created(fn (Model $model) => $observer->created($model));
        static::updated(fn (Model $model) => $observer->updated($model));
        static::deleted(fn (Model $model) => $observer->deleted($model));
    }
}
