<?php

namespace App\Telegram\Commands;

use App\Models\RoomType;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\AvailabilityService;
use App\Telegram\TelegramResponder;
use Carbon\Carbon;

class AvailableCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private AvailabilityService $availabilityService,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('reservations.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.view')) {
            return;
        }

        if (count($args) < 2) {
            $this->reply($tgUser, 'Usage: /available &lt;checkin&gt; &lt;checkout&gt; [room_type_code]');

            return;
        }

        try {
            $checkin = Carbon::parse($args[0])->startOfDay();
            $checkout = Carbon::parse($args[1])->startOfDay();
        } catch (\Exception) {
            $this->reply($tgUser, '❌ Invalid date format. Use YYYY-MM-DD.');

            return;
        }

        if ($checkout->lte($checkin)) {
            $this->reply($tgUser, '❌ Check-out must be after check-in.');

            return;
        }

        $this->setHotelContext($tgUser);

        $availability = $this->availabilityService->getAvailability(
            $checkin,
            $checkout,
            $tgUser->hotel_id,
        );

        $roomTypeFilter = isset($args[2]) ? strtoupper($args[2]) : null;

        if ($roomTypeFilter !== null) {
            $availability = array_values(array_filter(
                $availability,
                fn (array $row) => strtoupper($row['code']) === $roomTypeFilter,
            ));
        }

        if (empty($availability)) {
            $this->reply($tgUser, 'No room types found for the selected criteria.');

            return;
        }

        $nights = $checkin->diffInDays($checkout);
        $header = sprintf(
            "📅 %s – %s (%d night%s)\n",
            $checkin->format('d M'),
            $checkout->format('d M Y'),
            $nights,
            $nights === 1 ? '' : 's',
        );

        $lines = collect($availability)->map(function (array $row) {
            $roomType = RoomType::query()->find($row['room_type_id']);
            $rate = $roomType ? $this->formatIdr($roomType->base_rate) : 'N/A';

            return sprintf(
                '🛏 %s: %d available (of %d) — %s/night',
                $row['name'],
                $row['available_count'],
                $row['total_count'],
                $rate,
            );
        })->implode("\n");

        $this->reply($tgUser, $header."\n".$lines);
    }
}
