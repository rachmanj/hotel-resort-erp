<?php

namespace App\Http\Controllers;

use App\Enums\HousekeepingStatus;
use App\Services\HousekeepingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingController extends Controller
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    public function index(Request $request): Response
    {
        $fullBoard = $this->housekeepingService->getRoomStatusBoard();
        $filter = $request->string('filter')->toString();
        $board = $fullBoard;

        if ($filter !== '' && $filter !== 'all') {
            $board = $fullBoard->filter(fn (array $room) => $room['housekeeping_status'] === $filter);
        }

        $columns = collect(HousekeepingStatus::cases())
            ->reject(fn (HousekeepingStatus $status) => $status === HousekeepingStatus::OutOfOrder)
            ->map(fn (HousekeepingStatus $status) => [
                'key' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
                'rooms' => $board->where('housekeeping_status', $status->value)->values(),
            ]);

        $outOfOrder = $fullBoard->where('housekeeping_status', HousekeepingStatus::OutOfOrder->value)->values();

        return Inertia::render('Housekeeping/Index', [
            'columns' => $columns->values(),
            'outOfOrderRooms' => $outOfOrder,
            'filters' => [
                'filter' => $filter ?: 'all',
            ],
            'statusOptions' => collect(HousekeepingStatus::cases())->map(fn (HousekeepingStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ]),
            'summary' => [
                'total' => $fullBoard->count(),
                'dirty' => $fullBoard->where('housekeeping_status', HousekeepingStatus::Dirty->value)->count(),
                'cleaning' => $fullBoard->where('housekeeping_status', HousekeepingStatus::Cleaning->value)->count(),
                'clean' => $fullBoard->where('housekeeping_status', HousekeepingStatus::Clean->value)->count(),
                'ready' => $fullBoard->where('housekeeping_status', HousekeepingStatus::Ready->value)->count(),
                'out_of_order' => $outOfOrder->count(),
            ],
        ]);
    }
}
