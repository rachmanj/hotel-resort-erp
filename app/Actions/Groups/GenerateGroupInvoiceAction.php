<?php

namespace App\Actions\Groups;

use App\Enums\ArInvoiceStatus;
use App\Enums\FolioType;
use App\Enums\GroupInvoiceMode;
use App\Models\ArInvoice;
use App\Models\Folio;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\FolioPostingService;
use App\Services\GroupBookingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateGroupInvoiceAction
{
    public function __construct(
        private FolioPostingService $folioPostingService,
        private GroupBookingService $groupBookingService,
    ) {}

    /**
     * @param  list<int>|null  $folioIds  Required when invoice_mode = split
     * @return array{mode: string, invoices: list<array{id: int, invoice_no: string, folio_ids: list<int>}>}
     */
    public function __invoke(
        ReservationGroup $group,
        ?GroupInvoiceMode $mode = null,
        ?array $folioIds = null,
        ?User $performedBy = null,
    ): array {
        $mode ??= $group->invoice_mode;

        return match ($mode) {
            GroupInvoiceMode::PerRoom => $this->generatePerRoom($group, $performedBy),
            GroupInvoiceMode::Consolidated => $this->generateConsolidated($group, $performedBy),
            GroupInvoiceMode::Split => $this->generateSplit($group, $folioIds ?? [], $performedBy),
        };
    }

    /**
     * @return array{mode: string, invoices: list<array{id: int, invoice_no: string, folio_ids: list<int>}>}
     */
    private function generatePerRoom(ReservationGroup $group, ?User $performedBy): array
    {
        $folios = $this->getMemberMasterFolios($group);
        $invoices = [];

        foreach ($folios as $folio) {
            if ($group->company_id === null) {
                continue;
            }

            $invoice = $this->createArInvoiceForFolios($group, collect([$folio]));
            $invoices[] = [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'folio_ids' => [$folio->id],
            ];
        }

        $this->logInvoiceGeneration($group, 'per_room', $performedBy);

        return ['mode' => 'per_room', 'invoices' => $invoices];
    }

    /**
     * @return array{mode: string, invoices: list<array{id: int, invoice_no: string, folio_ids: list<int>}>}
     */
    private function generateConsolidated(ReservationGroup $group, ?User $performedBy): array
    {
        if ($group->company_id === null) {
            throw new InvalidArgumentException('Consolidated invoicing requires a company on the group.');
        }

        $folios = $this->getMemberMasterFolios($group);

        if ($folios->isEmpty()) {
            throw new InvalidArgumentException('No member folios found for consolidated invoice.');
        }

        $invoice = $this->createArInvoiceForFolios($group, $folios);

        $this->logInvoiceGeneration($group, 'consolidated', $performedBy);

        return [
            'mode' => 'consolidated',
            'invoices' => [[
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'folio_ids' => $folios->pluck('id')->all(),
            ]],
        ];
    }

    /**
     * @param  list<int>  $folioIds
     * @return array{mode: string, invoices: list<array{id: int, invoice_no: string, folio_ids: list<int>}>}
     */
    private function generateSplit(ReservationGroup $group, array $folioIds, ?User $performedBy): array
    {
        if ($group->company_id === null) {
            throw new InvalidArgumentException('Split invoicing requires a company on the group.');
        }

        if ($folioIds === []) {
            throw new InvalidArgumentException('Folio IDs are required for split invoicing.');
        }

        $folios = Folio::query()
            ->whereIn('id', $folioIds)
            ->where('type', FolioType::Master->value)
            ->get();

        $invoice = $this->createArInvoiceForFolios($group, $folios);

        $this->logInvoiceGeneration($group, 'split', $performedBy);

        return [
            'mode' => 'split',
            'invoices' => [[
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'folio_ids' => $folios->pluck('id')->all(),
            ]],
        ];
    }

    /**
     * @return Collection<int, Folio>
     */
    private function getMemberMasterFolios(ReservationGroup $group): Collection
    {
        $reservationIds = $group->reservations()->pluck('id');

        return Folio::query()
            ->whereIn('reservation_id', $reservationIds)
            ->where('type', FolioType::Master->value)
            ->get();
    }

    /**
     * @param  Collection<int, Folio>  $folios
     */
    private function createArInvoiceForFolios(ReservationGroup $group, Collection $folios): ArInvoice
    {
        return DB::transaction(function () use ($group, $folios): ArInvoice {
            $totalAmount = 0.0;

            foreach ($folios as $folio) {
                $totalAmount += $this->folioPostingService->getChargesTotal($folio);
            }

            $companyId = $group->company_id;

            if ($companyId === null) {
                throw new InvalidArgumentException('Company is required for AR invoice generation.');
            }

            $invoice = ArInvoice::query()->create([
                'hotel_id' => $group->hotel_id,
                'invoice_no' => $this->generateInvoiceNumber(),
                'company_id' => $companyId,
                'period_start' => $group->arrival_date ?? now()->toDateString(),
                'period_end' => $group->departure_date ?? now()->toDateString(),
                'total_amount' => round($totalAmount, 2),
                'paid_amount' => 0,
                'status' => ArInvoiceStatus::Open->value,
                'due_date' => now()->addDays(30)->toDateString(),
                'issued_at' => now(),
            ]);

            $invoice->folios()->attach($folios->pluck('id'));

            return $invoice;
        });
    }

    private function generateInvoiceNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "AR-{$datePrefix}-";

        $lastCode = ArInvoice::query()
            ->withoutGlobalScope('hotel')
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $sequence = 1;
        if ($lastCode !== null) {
            $sequence = (int) substr($lastCode, -4) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function logInvoiceGeneration(ReservationGroup $group, string $mode, ?User $performedBy): void
    {
        if ($performedBy === null) {
            return;
        }

        ActivityLogObserver::logCustom(
            $group,
            'invoice_generated',
            "Group {$group->group_code} {$mode} invoice generated by {$performedBy->name}",
            $performedBy->id,
        );
    }
}
