<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\Reports\AdrRevParReport;
use App\Services\Reports\DailyRevenueReport;
use App\Services\Reports\OccupancyReport;
use App\Telegram\TelegramResponder;
use Carbon\Carbon;

class ReportCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private DailyRevenueReport $dailyRevenueReport,
        private OccupancyReport $occupancyReport,
        private AdrRevParReport $adrRevParReport,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && ($tgUser->user?->can('reports.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'reports.view')) {
            return;
        }

        $this->setHotelContext($tgUser);

        $type = strtolower($args[0] ?? 'daily');
        $dateArg = $args[1] ?? null;
        $date = $this->parseDate($dateArg);

        $hotelId = (int) $tgUser->hotel_id;
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();

        match ($type) {
            'occupancy' => $this->replyOccupancy($tgUser, $hotelId, $startDate, $endDate),
            'revenue', 'daily' => $this->replyDailyRevenue($tgUser, $hotelId, $startDate, $endDate),
            'adr', 'revpar', 'adr-revpar' => $this->replyAdrRevPar($tgUser, $hotelId, $startDate, $endDate),
            default => $this->reply($tgUser, "Unknown report type: {$type}\nUsage: /report daily|occupancy|revenue|adr [date]"),
        };
    }

    private function replyDailyRevenue(TelegramUser $tgUser, int $hotelId, Carbon $startDate, Carbon $endDate): void
    {
        $report = $this->dailyRevenueReport->generate($hotelId, $startDate, $endDate);

        $lines = [
            '📊 *Daily Revenue Report*',
            $startDate->format('d M Y'),
            '',
            '*By Department*',
        ];

        foreach ($report['by_department'] as $row) {
            if ($row['amount'] > 0) {
                $lines[] = "{$row['label']}: {$this->formatIdr($row['amount'])}";
            }
        }

        $lines[] = '';
        $lines[] = '*By Payment Method*';

        foreach ($report['by_payment_method'] as $row) {
            $lines[] = "{$row['label']}: {$this->formatIdr($row['amount'])}";
        }

        $lines[] = '';
        $lines[] = "*Total Revenue:* {$this->formatIdr($report['totals']['revenue'])}";
        $lines[] = "*Total Payments:* {$this->formatIdr($report['totals']['payments'])}";

        $this->reply($tgUser, implode("\n", $lines));
    }

    private function replyOccupancy(TelegramUser $tgUser, int $hotelId, Carbon $startDate, Carbon $endDate): void
    {
        $report = $this->occupancyReport->generate($hotelId, $startDate, $endDate);
        $summary = $report['summary'];

        $lines = [
            '🏨 *Occupancy Report*',
            $startDate->format('d M Y'),
            '',
            "Occupancy: *{$summary['occupancy_pct']}%*",
            "Rooms Sold: {$summary['rooms_sold']}",
            "Rooms Available: {$summary['rooms_available']}",
            "Total Rooms: {$summary['total_rooms']}",
            '',
            '*By Room Type*',
        ];

        foreach ($report['by_room_type']->take(8) as $row) {
            $lines[] = "{$row['room_type_name']}: {$row['occupancy_pct']}% ({$row['rooms_sold']}/{$row['rooms_available']})";
        }

        if ($report['by_room_type']->count() > 8) {
            $lines[] = '_...and more room types_';
        }

        $this->reply($tgUser, implode("\n", $lines));
    }

    private function replyAdrRevPar(TelegramUser $tgUser, int $hotelId, Carbon $startDate, Carbon $endDate): void
    {
        $report = $this->adrRevParReport->generate($hotelId, $startDate, $endDate);
        $current = $report['current'];
        $comparison = $report['comparison'];
        $variance = $report['variance'];

        $lines = [
            '📈 *ADR / RevPAR Report*',
            $startDate->format('d M Y'),
            '',
            '*Current*',
            "Room Revenue: {$this->formatIdr($current['room_revenue'])}",
            "ADR: {$this->formatIdr($current['adr'])}",
            "RevPAR: {$this->formatIdr($current['revpar'])}",
            "Occupancy: {$current['occupancy_pct']}%",
            '',
            '*Previous Period*',
            "ADR: {$this->formatIdr($comparison['adr'])}",
            "RevPAR: {$this->formatIdr($comparison['revpar'])}",
            "Occupancy: {$comparison['occupancy_pct']}%",
            '',
            '*Variance*',
            'ADR: '.$this->formatVariance($variance['adr_pct']),
            'RevPAR: '.$this->formatVariance($variance['revpar_pct']),
            'Occupancy: '.$this->formatVariance($variance['occupancy_pct']),
        ];

        $this->reply($tgUser, implode("\n", $lines));
    }

    private function parseDate(?string $dateArg): Carbon
    {
        if ($dateArg === null) {
            return now();
        }

        try {
            return Carbon::parse($dateArg);
        } catch (\Throwable) {
            return now();
        }
    }

    private function formatVariance(?float $pct): string
    {
        if ($pct === null) {
            return '–';
        }

        $sign = $pct >= 0 ? '+' : '';

        return "{$sign}{$pct}%";
    }
}
