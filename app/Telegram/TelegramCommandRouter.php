<?php

namespace App\Telegram;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Telegram\Commands\ApproveRequisitionCommand;
use App\Telegram\Commands\AvailableCommand;
use App\Telegram\Commands\BalanceSheetCommand;
use App\Telegram\Commands\CancelReservationCommand;
use App\Telegram\Commands\CheckInCommand;
use App\Telegram\Commands\CheckOutCommand;
use App\Telegram\Commands\EditReservationCommand;
use App\Telegram\Commands\GlCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\KdsCommand;
use App\Telegram\Commands\LinkCommand;
use App\Telegram\Commands\LowOrdersCommand;
use App\Telegram\Commands\MaintCommand;
use App\Telegram\Commands\MyRoomsCommand;
use App\Telegram\Commands\NewReservationCommand;
use App\Telegram\Commands\PnlCommand;
use App\Telegram\Commands\ReportCommand;
use App\Telegram\Commands\RoomsCommand;
use App\Telegram\Commands\RoomStatusCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Commands\StockCommand;
use App\Telegram\Commands\SwitchPropertyCommand;
use App\Telegram\Commands\TrialBalanceCommand;
use App\Telegram\Commands\UnlinkCommand;
use App\Telegram\Commands\WhoAmICommand;
use App\Telegram\Commands\WorkOrdersCommand;
use App\Telegram\Contracts\TelegramCommand;

class TelegramCommandRouter
{
    /**
     * @var array<string, array{class: class-string<TelegramCommand>, description: string}>
     */
    private const COMMANDS = [
        '/start' => ['class' => StartCommand::class, 'description' => 'Welcome & link instructions'],
        '/link' => ['class' => LinkCommand::class, 'description' => 'Link your account'],
        '/unlink' => ['class' => UnlinkCommand::class, 'description' => 'Unlink your account'],
        '/whoami' => ['class' => WhoAmICommand::class, 'description' => 'Show linked identity'],
        '/switchproperty' => ['class' => SwitchPropertyCommand::class, 'description' => 'Switch active property'],
        '/rooms' => ['class' => RoomsCommand::class, 'description' => 'List rooms'],
        '/available' => ['class' => AvailableCommand::class, 'description' => 'Check availability'],
        '/newres' => ['class' => NewReservationCommand::class, 'description' => 'Create reservation'],
        '/editres' => ['class' => EditReservationCommand::class, 'description' => 'Edit reservation'],
        '/cancelres' => ['class' => CancelReservationCommand::class, 'description' => 'Cancel reservation'],
        '/roomstatus' => ['class' => RoomStatusCommand::class, 'description' => 'Check room status'],
        '/myrooms' => ['class' => MyRoomsCommand::class, 'description' => "Today's rooms"],
        '/checkin' => ['class' => CheckInCommand::class, 'description' => 'Check in a guest'],
        '/checkout' => ['class' => CheckOutCommand::class, 'description' => 'Check out a guest'],
        '/kds' => ['class' => KdsCommand::class, 'description' => 'Kitchen display summary'],
        '/maint' => ['class' => MaintCommand::class, 'description' => 'Raise maintenance ticket'],
        '/workorders' => ['class' => WorkOrdersCommand::class, 'description' => 'List work orders'],
        '/stock' => ['class' => StockCommand::class, 'description' => 'Check stock level'],
        '/loworders' => ['class' => LowOrdersCommand::class, 'description' => 'List low stock items'],
        '/approve' => ['class' => ApproveRequisitionCommand::class, 'description' => 'Approve purchase requisition'],
        '/gl' => ['class' => GlCommand::class, 'description' => 'GL account transactions'],
        '/trialbalance' => ['class' => TrialBalanceCommand::class, 'description' => 'Trial balance report'],
        '/pnl' => ['class' => PnlCommand::class, 'description' => 'Income statement (P&L)'],
        '/balancesheet' => ['class' => BalanceSheetCommand::class, 'description' => 'Balance sheet report'],
        '/report' => ['class' => ReportCommand::class, 'description' => 'Operational reports (daily/occupancy/revenue)'],
        '/help' => ['class' => HelpCommand::class, 'description' => 'Show available commands'],
    ];

    public function __construct(
        private TelegramConversationManager $conversationManager,
        private TelegramResponder $responder,
    ) {}

    /**
     * @return array<string, array{class: class-string<TelegramCommand>, description: string}>
     */
    public static function commandDescriptions(): array
    {
        return self::COMMANDS;
    }

    public function route(
        string $command,
        array $args,
        TelegramUser $tgUser,
        ?TelegramConversationState $state,
    ): void {
        $command = strtolower($command);

        if (! isset(self::COMMANDS[$command])) {
            $this->responder->sendMessage(
                (int) $tgUser->chat_id,
                'Unknown command. Type /help for available commands.',
            );

            return;
        }

        $handler = app(self::COMMANDS[$command]['class']);

        if (! $handler->authorize($tgUser)) {
            $this->responder->sendMessage(
                (int) $tgUser->chat_id,
                "⛔ You don't have permission to use {$command}.",
            );

            return;
        }

        $handler->handle($args, $tgUser, $state);
    }

    public function routeFlowStep(TelegramUser $tgUser, TelegramConversationState $state, string $text): void
    {
        $flowHandlers = [
            'new_reservation' => NewReservationCommand::class,
            'edit_reservation' => EditReservationCommand::class,
            'cancel_reservation' => CancelReservationCommand::class,
        ];

        $handlerClass = $flowHandlers[$state->flow] ?? null;

        if ($handlerClass === null) {
            $this->conversationManager->cancelFlow($state);
            $this->responder->sendMessage((int) $tgUser->chat_id, 'Session expired. Please start again.');

            return;
        }

        $handler = app($handlerClass);

        if (method_exists($handler, 'handleFlowStep')) {
            $handler->handleFlowStep($tgUser, $state, $text);
        }
    }

    public function routeCallback(TelegramUser $tgUser, string $data, ?TelegramConversationState $state): void
    {
        $parts = explode(':', $data);
        $prefix = $parts[0] ?? '';

        match ($prefix) {
            'switch' => app(SwitchPropertyCommand::class)->handleCallback($tgUser, $parts[1] ?? ''),
            'rooms' => app(RoomsCommand::class)->handleCallback(
                $tgUser,
                $parts[1] ?? 'all',
                (int) ($parts[2] ?? 1),
            ),
            'newres' => $this->handleNewResCallback($tgUser, $state, $parts[1] ?? '', $parts[2] ?? null),
            'editres' => $this->handleEditResCallback($tgUser, $state, $parts[1] ?? '', $parts[2] ?? null),
            default => null,
        };
    }

    private function handleNewResCallback(
        TelegramUser $tgUser,
        ?TelegramConversationState $state,
        string $action,
        ?string $value,
    ): void {
        if ($state === null || $state->flow !== 'new_reservation') {
            return;
        }

        app(NewReservationCommand::class)->handleCallback($tgUser, $state, $action, $value);
    }

    private function handleEditResCallback(
        TelegramUser $tgUser,
        ?TelegramConversationState $state,
        string $action,
        ?string $value,
    ): void {
        if ($state === null || $state->flow !== 'edit_reservation') {
            return;
        }

        $handler = app(EditReservationCommand::class);

        if ($action === 'rt' && $value !== null) {
            $handler->handleRoomTypeCallback($tgUser, $state, (int) $value);

            return;
        }

        $handler->handleCallback($tgUser, $state, $action);
    }
}
