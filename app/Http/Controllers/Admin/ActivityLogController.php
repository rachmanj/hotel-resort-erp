<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $hotelId = session('current_hotel_id');

        $logs = ActivityLog::query()
            ->with(['user:id,name', 'subject'])
            ->when($hotelId !== null, fn ($query) => $query->where('hotel_id', $hotelId))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')))
            ->when($request->filled('subject_type'), fn ($query) => $query->where('subject_type', $request->string('subject_type')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user?->only(['id', 'name']),
                'event' => $log->event,
                'subject_type' => $this->formatSubjectType($log->subject_type),
                'description' => $this->buildDescription($log),
                'properties' => $log->properties,
            ]);

        $subjectTypes = ActivityLog::query()
            ->when($hotelId !== null, fn ($query) => $query->where('hotel_id', $hotelId))
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn (string $type) => [
                'value' => $type,
                'label' => $this->formatSubjectType($type),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['date_from', 'date_to', 'user_id', 'event', 'subject_type']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'events' => ['created', 'updated', 'deleted', 'cancelled', 'checked_in', 'checked_out'],
            'subjectTypes' => $subjectTypes,
        ]);
    }

    private function formatSubjectType(string $subjectType): string
    {
        $className = class_basename($subjectType);

        return preg_replace('/(?<!^)[A-Z]/', ' $0', $className) ?? $className;
    }

    private function buildDescription(ActivityLog $log): string
    {
        $properties = $log->properties ?? [];

        if (isset($properties['description']) && is_string($properties['description'])) {
            return $properties['description'];
        }

        $subject = $log->subject;

        if ($log->event === 'cancelled' && $subject instanceof Reservation) {
            $reason = $properties['cancellation_reason'] ?? $subject->cancelled_reason ?? 'N/A';

            return "Reservation {$subject->reservation_code} cancelled · reason: {$reason}";
        }

        if ($log->event === 'created' && $subject instanceof Reservation) {
            $subject->loadMissing('guest');
            $guestName = $subject->guest?->full_name ?? 'Guest';

            return "Reservation {$subject->reservation_code} created for {$guestName}";
        }

        if ($log->event === 'created' && $subject instanceof Payment) {
            $subject->loadMissing('folio');
            $amount = number_format((float) $subject->amount, 0, ',', '.');
            $folioNo = $subject->folio?->folio_no ?? 'N/A';

            return "Payment Rp{$amount} received for folio {$folioNo}";
        }

        if ($log->event === 'updated' && $subject instanceof Reservation) {
            $changes = $properties['changes'] ?? [];
            if (isset($changes['status']) && $changes['status'] === 'cancelled') {
                $reason = $properties['cancellation_reason'] ?? $subject->cancelled_reason ?? 'N/A';

                return "Reservation {$subject->reservation_code} cancelled · reason: {$reason}";
            }
        }

        $modelName = $this->formatSubjectType($log->subject_type);
        $eventLabel = str_replace('_', ' ', $log->event);

        if ($log->event === 'updated' && isset($properties['changes']) && is_array($properties['changes'])) {
            $fields = implode(', ', array_keys($properties['changes']));
            $identifier = $this->subjectIdentifier($subject);

            return $identifier !== null
                ? "{$modelName} \"{$identifier}\" {$eventLabel}: {$fields}"
                : "{$modelName} {$eventLabel}: {$fields}";
        }

        if ($log->event === 'deleted') {
            $attributes = $subject !== null ? $subject->getAttributes() : ($properties['attributes'] ?? []);
            $identifier = $this->extractIdentifier($attributes);

            return $identifier !== null
                ? "{$modelName} \"{$identifier}\" {$eventLabel}"
                : "{$modelName} {$eventLabel}";
        }

        $identifier = $this->subjectIdentifier($subject);

        return $identifier !== null
            ? "{$modelName} \"{$identifier}\" {$eventLabel}"
            : "{$modelName} {$eventLabel}";
    }

    private function subjectIdentifier(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        return $this->extractIdentifier($subject->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function extractIdentifier(array $attributes): ?string
    {
        foreach (['reservation_code', 'folio_no', 'requisition_no', 'order_no', 'work_order_no', 'code', 'number', 'name', 'full_name', 'title', 'email', 'sku'] as $attribute) {
            if (isset($attributes[$attribute]) && $attributes[$attribute] !== null && $attributes[$attribute] !== '') {
                return (string) $attributes[$attribute];
            }
        }

        return null;
    }
}
