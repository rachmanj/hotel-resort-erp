<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOtaFeeRequest;
use App\Http\Requests\Admin\UpdateOtaFeeRequest;
use App\Models\OtaFee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OtaFeeController extends Controller
{
    public function index(Request $request): Response
    {
        $otaFees = OtaFee::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (OtaFee $otaFee) => [
                'id' => $otaFee->id,
                'code' => $otaFee->code,
                'name' => $otaFee->name,
                'fee_type' => $otaFee->fee_type->value,
                'fee_type_label' => $otaFee->fee_type->label(),
                'base_fee_pct' => $otaFee->base_fee_pct,
                'variable_fee_pct' => $otaFee->variable_fee_pct,
                'flat_fee_per_room_night' => $otaFee->flat_fee_per_room_night,
                'is_active' => $otaFee->is_active,
            ]);

        return Inertia::render('Admin/OtaFees/Index', [
            'otaFees' => $otaFees,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreOtaFeeRequest $request): RedirectResponse
    {
        OtaFee::query()->create($request->validated());

        return back()->with('success', 'OTA fee created successfully.');
    }

    public function update(UpdateOtaFeeRequest $request, OtaFee $otaFee): RedirectResponse
    {
        $otaFee->update($request->validated());

        return back()->with('success', 'OTA fee updated successfully.');
    }

    public function destroy(OtaFee $otaFee): RedirectResponse
    {
        if ($otaFee->charges()->exists()) {
            return back()->with('error', 'Cannot delete OTA fee with existing charges.');
        }

        $otaFee->delete();

        return back()->with('success', 'OTA fee deleted successfully.');
    }
}
