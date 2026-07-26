<?php

namespace App\Telegram\Commands;

use App\Enums\PurchaseRequisitionStatus;
use App\Models\PurchaseRequisition;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\PurchaseRequisitionService;
use App\Telegram\TelegramResponder;

class ApproveRequisitionCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private PurchaseRequisitionService $requisitionService,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && ($tgUser->user?->can('purchasing.approve') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->setHotelContext($tgUser);

        if ($args === []) {
            $this->reply($tgUser, 'Usage: /approve {requisition_no}');

            return;
        }

        $requisitionNo = strtoupper($args[0]);

        $requisition = PurchaseRequisition::query()
            ->where('requisition_no', $requisitionNo)
            ->first();

        if ($requisition === null) {
            $this->reply($tgUser, "❌ Requisition {$requisitionNo} not found.");

            return;
        }

        if ($requisition->status !== PurchaseRequisitionStatus::PendingApproval) {
            $this->reply($tgUser, "❌ Requisition {$requisitionNo} is not pending approval (status: {$requisition->status->label()}).");

            return;
        }

        $this->requisitionService->approve($requisition, $tgUser->user);

        $this->reply($tgUser, "✅ Requisition {$requisitionNo} approved.");
    }
}
