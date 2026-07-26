<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $assets = Asset::query()
            ->with(['room:id,number'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('asset_type'), fn ($q) => $q->where('asset_type', $request->string('asset_type')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Asset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'asset_type' => $asset->asset_type->value,
                'asset_type_label' => $asset->asset_type->label(),
                'status' => $asset->status->value,
                'status_label' => $asset->status->label(),
                'room' => $asset->room?->only(['id', 'number']),
                'location' => $asset->location,
                'purchased_at' => $asset->purchased_at?->toDateString(),
                'warranty_until' => $asset->warranty_until?->toDateString(),
            ]);

        return Inertia::render('Maintenance/Assets/Index', [
            'assets' => $assets,
            'filters' => $request->only(['status', 'asset_type']),
            'typeOptions' => collect(AssetType::cases())->map(fn (AssetType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'statusOptions' => collect(AssetStatus::cases())->map(fn (AssetStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'rooms' => Room::query()->orderBy('number')->get(['id', 'number']),
        ]);
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        Asset::query()->create($request->validated());

        return back()->with('success', 'Asset created.');
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return back()->with('success', 'Asset updated.');
    }
}
