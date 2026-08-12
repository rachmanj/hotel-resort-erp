<?php

namespace App\Telegram\Commands;

use App\Models\RoomType;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\PromotionEngine;
use App\Telegram\TelegramResponder;
use Carbon\Carbon;

class PromoCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private PromotionEngine $promotionEngine,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('promotions.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'promotions.view')) {
            return;
        }

        if ($tgUser->hotel_id === null) {
            $this->reply($tgUser, '❌ No property selected. Use /switchproperty first.');

            return;
        }

        if (count($args) < 3) {
            $this->reply(
                $tgUser,
                "Usage: /promo <code> <checkin> <checkout> [room_type_code]\nExample: /promo SUMMER25 2026-08-15 2026-08-17 DLX",
            );

            return;
        }

        $code = $args[0];

        try {
            $checkin = Carbon::parse($args[1])->startOfDay();
            $checkout = Carbon::parse($args[2])->startOfDay();
        } catch (\Exception) {
            $this->reply($tgUser, '❌ Invalid date format. Use YYYY-MM-DD.');

            return;
        }

        if ($checkout->lte($checkin)) {
            $this->reply($tgUser, '❌ Check-out must be after check-in.');

            return;
        }

        $this->setHotelContext($tgUser);

        $roomTypeCode = $args[3] ?? null;
        $roomTypeQuery = RoomType::query();

        if ($roomTypeCode !== null) {
            $roomType = $roomTypeQuery->where('code', strtoupper($roomTypeCode))->first();
            if ($roomType === null) {
                $this->reply($tgUser, "❌ Room type code '{$roomTypeCode}' not found.");

                return;
            }
        } else {
            $roomType = $roomTypeQuery->orderBy('name')->first();
            if ($roomType === null) {
                $this->reply($tgUser, '❌ No room types configured for this property.');

                return;
            }
        }

        $nights = max(1, $checkin->diffInDays($checkout));
        $baseRate = $this->promotionEngine->resolveBaseNightlyRate(null, $roomType);

        $applicable = $this->promotionEngine->findApplicable(
            $roomType,
            $checkin,
            $checkout,
            null,
            $code,
            $tgUser->hotel_id,
        );

        if ($applicable->isEmpty()) {
            $this->reply($tgUser, "❌ Code '{$code}' is not valid for {$roomType->name} on these dates.");

            return;
        }

        $resolved = $this->promotionEngine->resolveBestRate(
            $roomType,
            $baseRate,
            $applicable,
            $nights,
            $code,
        );

        $lines = collect($applicable)->map(fn ($p) => "• {$p->name} — {$p->discountSummary()}")->implode("\n");

        $message = sprintf(
            "🏷 Promo check: %s\n📅 %s → %s (%d night%s)\n🛏 %s\n\nApplicable:\n%s\n\nBase: %s/night\nAfter promo: %s/night\nTotal discount: %s",
            strtoupper($code),
            $checkin->format('d M Y'),
            $checkout->format('d M Y'),
            $nights,
            $nights === 1 ? '' : 's',
            $roomType->name,
            $lines,
            $this->formatIdr($baseRate),
            $this->formatIdr($resolved['nightly_rate']),
            $this->formatIdr($resolved['discount_amount']),
        );

        $this->reply($tgUser, $message);
    }
}
