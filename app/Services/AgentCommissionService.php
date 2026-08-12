<?php

namespace App\Services;

use App\Enums\AgentCommissionStatus;
use App\Enums\CommissionBasis;
use App\Enums\FolioItemType;
use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Accounting\GlPostingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AgentCommissionService
{
    public function __construct(
        private FolioPostingService $folioPostingService,
        private GlPostingService $glPostingService,
    ) {}

    /**
     * @return array{base_amount: float, commission_amount: float}
     */
    public function calculateForReservation(Reservation $reservation, Agent $agent): array
    {
        $folio = Folio::query()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'master')
            ->first();

        if ($folio === null) {
            return ['base_amount' => 0.0, 'commission_amount' => 0.0];
        }

        $baseAmount = $this->calculateBaseAmount($folio, $agent->commission_basis);
        $commissionAmount = round($baseAmount * ((float) $agent->commission_percent / 100), 2);

        return [
            'base_amount' => $baseAmount,
            'commission_amount' => $commissionAmount,
        ];
    }

    public function accrue(Reservation $reservation, Agent $agent, ?User $performedBy = null): AgentCommission
    {
        if (AgentCommission::query()->where('reservation_id', $reservation->id)->exists()) {
            return AgentCommission::query()->where('reservation_id', $reservation->id)->firstOrFail();
        }

        return DB::transaction(function () use ($reservation, $agent): AgentCommission {
            $folio = Folio::query()
                ->where('reservation_id', $reservation->id)
                ->where('type', 'master')
                ->first();

            $calculation = $this->calculateForReservation($reservation, $agent);

            if ($calculation['commission_amount'] <= 0) {
                throw new InvalidArgumentException('Commission amount must be greater than zero.');
            }

            $commission = AgentCommission::query()->create([
                'agent_id' => $agent->id,
                'reservation_id' => $reservation->id,
                'folio_id' => $folio?->id,
                'base_amount' => $calculation['base_amount'],
                'commission_percent' => $agent->commission_percent,
                'commission_amount' => $calculation['commission_amount'],
                'status' => AgentCommissionStatus::Pending->value,
                'earned_at' => now(),
            ]);

            $expenseAccount = $this->glPostingService->findAccountByCode($reservation->hotel_id, '6-3300');
            $payableAccount = $this->glPostingService->findAccountByCode($reservation->hotel_id, '2-1400');

            $this->glPostingService->post([
                [
                    'hotel_id' => $reservation->hotel_id,
                    'chart_of_account_id' => $expenseAccount->id,
                    'transaction_date' => now()->toDateString(),
                    'debit' => $calculation['commission_amount'],
                    'credit' => 0,
                    'description' => "Agent commission accrual — {$agent->name} ({$reservation->reservation_code})",
                    'reference_number' => $reservation->reservation_code,
                    'source_type' => AgentCommission::class,
                    'source_id' => $commission->id,
                ],
                [
                    'hotel_id' => $reservation->hotel_id,
                    'chart_of_account_id' => $payableAccount->id,
                    'transaction_date' => now()->toDateString(),
                    'debit' => 0,
                    'credit' => $calculation['commission_amount'],
                    'description' => "Agent commission payable — {$agent->name} ({$reservation->reservation_code})",
                    'reference_number' => $reservation->reservation_code,
                    'source_type' => AgentCommission::class,
                    'source_id' => $commission->id,
                ],
            ]);

            return $commission;
        });
    }

    /**
     * @return array{
     *     agent: array{id: int, name: string, code: string},
     *     from: string,
     *     to: string,
     *     total_pending: float,
     *     total_invoiced: float,
     *     total_paid: float,
     *     commissions: list<array<string, mixed>>
     * }
     */
    public function generateStatement(Agent $agent, CarbonInterface $from, CarbonInterface $to): array
    {
        $commissions = AgentCommission::query()
            ->with(['reservation:id,reservation_code,arrival_date,departure_date'])
            ->where('agent_id', $agent->id)
            ->whereBetween('earned_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('earned_at')
            ->get();

        return [
            'agent' => $agent->only(['id', 'name', 'code']),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_pending' => (float) $commissions->where('status', AgentCommissionStatus::Pending)->sum('commission_amount'),
            'total_invoiced' => (float) $commissions->where('status', AgentCommissionStatus::Invoiced)->sum('commission_amount'),
            'total_paid' => (float) $commissions->where('status', AgentCommissionStatus::Paid)->sum('commission_amount'),
            'commissions' => $commissions->map(fn (AgentCommission $c) => [
                'id' => $c->id,
                'reservation_code' => $c->reservation?->reservation_code,
                'arrival_date' => $c->reservation?->arrival_date?->toDateString(),
                'departure_date' => $c->reservation?->departure_date?->toDateString(),
                'base_amount' => $c->base_amount,
                'commission_percent' => $c->commission_percent,
                'commission_amount' => $c->commission_amount,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'earned_at' => $c->earned_at?->toDateTimeString(),
            ])->all(),
        ];
    }

    private function calculateBaseAmount(Folio $folio, CommissionBasis $basis): float
    {
        $items = FolioItem::query()->where('folio_id', $folio->id)->get();

        return match ($basis) {
            CommissionBasis::Gross => (float) $items->sum(fn (FolioItem $item) => (float) $item->amount + (float) $item->tax_amount + (float) $item->service_charge_amount),
            CommissionBasis::NetRoom => (float) $items
                ->where('item_type', FolioItemType::Room->value)
                ->sum('amount'),
            CommissionBasis::NetRoomNoTax => (float) $items
                ->where('item_type', FolioItemType::Room->value)
                ->sum('amount'),
        };
    }
}
