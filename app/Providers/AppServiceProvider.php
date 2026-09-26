<?php

namespace App\Providers;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\FolioItem;
use App\Models\HousekeepingLog;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Observers\FolioItemGuestInvoiceObserver;
use App\Observers\HousekeepingLogObserver;
use App\Observers\ReservationProformaObserver;
use App\Observers\ReservationRoomProformaObserver;
use App\Telegram\Notifications\TelegramChannel;
use App\WhatsApp\Notifications\WhatsAppChannel;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        HousekeepingLog::observe(HousekeepingLogObserver::class);
        Reservation::observe(ReservationProformaObserver::class);
        ReservationRoom::observe(ReservationRoomProformaObserver::class);
        FolioItem::observe(FolioItemGuestInvoiceObserver::class);

        Notification::extend('telegram', fn () => app(TelegramChannel::class));

        Notification::extend('whatsapp', fn () => app(WhatsAppChannel::class));

        RouteFacade::bind('line', function (string $value, Route $route): BankReconciliationLine {
            $bankReconciliation = $route->parameter('bankReconciliation');

            if (! $bankReconciliation instanceof BankReconciliation) {
                $bankReconciliation = BankReconciliation::query()->findOrFail($bankReconciliation);
            }

            return BankReconciliationLine::query()
                ->where('bank_reconciliation_id', $bankReconciliation->id)
                ->whereKey($value)
                ->firstOrFail();
        });

        RouteFacade::bind('bookLine', function (string $value, Route $route): BankReconciliationBookLine {
            $bankReconciliation = $route->parameter('bankReconciliation');

            if (! $bankReconciliation instanceof BankReconciliation) {
                $bankReconciliation = BankReconciliation::query()->findOrFail($bankReconciliation);
            }

            return BankReconciliationBookLine::query()
                ->where('bank_reconciliation_id', $bankReconciliation->id)
                ->whereKey($value)
                ->firstOrFail();
        });

        RouteFacade::bind('match', function (string $value, Route $route): BankReconciliationMatch {
            $bankReconciliation = $route->parameter('bankReconciliation');

            if (! $bankReconciliation instanceof BankReconciliation) {
                $bankReconciliation = BankReconciliation::query()->findOrFail($bankReconciliation);
            }

            return BankReconciliationMatch::query()
                ->where('bank_reconciliation_id', $bankReconciliation->id)
                ->whereKey($value)
                ->firstOrFail();
        });
    }
}
