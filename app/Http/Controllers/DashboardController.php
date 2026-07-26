<?php

namespace App\Http\Controllers;

use App\Enums\HousekeepingStatus;
use App\Services\HousekeepingService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    public function index(): Response
    {
        $board = $this->housekeepingService->getRoomStatusBoard();

        return Inertia::render('Dashboard/Index', [
            'roomStatusSummary' => [
                'total' => $board->count(),
                'dirty' => $board->where('housekeeping_status', HousekeepingStatus::Dirty->value)->count(),
                'cleaning' => $board->where('housekeeping_status', HousekeepingStatus::Cleaning->value)->count(),
                'clean' => $board->where('housekeeping_status', HousekeepingStatus::Clean->value)->count(),
                'ready' => $board->where('housekeeping_status', HousekeepingStatus::Ready->value)->count(),
                'out_of_order' => $board->where('housekeeping_status', HousekeepingStatus::OutOfOrder->value)->count(),
            ],
            'rooms' => $board->map(fn (array $room) => [
                'id' => $room['id'],
                'number' => $room['number'],
                'housekeeping_status' => $room['housekeeping_status'],
                'housekeeping_status_label' => $room['housekeeping_status_label'],
                'housekeeping_status_color' => $room['housekeeping_status_color'],
            ])->values(),
        ]);
    }
}
