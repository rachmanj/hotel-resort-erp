<?php

namespace App\Http\Controllers;

use App\Enums\DivePackageType;
use App\Enums\DiveRateItemType;
use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\PostFolioChargeRequest;
use App\Http\Requests\PostFolioPaymentRequest;
use App\Models\DivePackage;
use App\Models\DiveRateItem;
use App\Models\Folio;
use App\Models\RevenueCategory;
use App\Services\FolioPostingService;
use App\Services\TaxCalculator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FolioController extends Controller
{
    public function show(Folio $folio, FolioPostingService $folioPostingService, TaxCalculator $taxCalculator): Response
    {
        $folio->load([
            'guest',
            'reservation.promotion',
            'reservation.promotionRedemptions.promotionCode',
            'company',
            'items.postedBy:id,name',
            'payments.receivedBy:id,name',
        ]);

        $balance = $folioPostingService->getBalance($folio);
        $chargesTotal = $folioPostingService->getChargesTotal($folio);
        $paymentsTotal = $folio->payments->sum(fn ($p) => $p->is_refund ? -(float) $p->amount : (float) $p->amount);

        return Inertia::render('Folios/Show', [
            'folio' => [
                'id' => $folio->id,
                'folio_no' => $folio->folio_no,
                'status' => $folio->status->value,
                'status_label' => $folio->status->label(),
                'type' => $folio->type->value,
                'opened_at' => $folio->opened_at?->toDateTimeString(),
                'closed_at' => $folio->closed_at?->toDateTimeString(),
                'guest' => $folio->guest?->only(['id', 'full_name', 'phone', 'email']),
                'reservation' => $folio->reservation ? [
                    'id' => $folio->reservation->id,
                    'reservation_code' => $folio->reservation->reservation_code,
                    'promotion' => $folio->reservation->promotion ? [
                        'name' => $folio->reservation->promotion->name,
                        'discount_summary' => $folio->reservation->promotion->discountSummary(),
                    ] : null,
                    'promotion_redemptions' => $folio->reservation->promotionRedemptions->map(fn ($r) => [
                        'promotion_name' => $r->promotion?->name,
                        'code' => $r->promotionCode?->code,
                        'discount_amount' => $r->discount_amount,
                    ]),
                ] : null,
                'company' => $folio->company?->only(['id', 'name']),
                'items' => $folio->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_type' => $item->item_type->value,
                    'item_type_label' => $item->item_type->label(),
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                    'dpp_amount' => $item->dpp_amount,
                    'tax_amount' => $item->tax_amount,
                    'service_charge_amount' => $item->service_charge_amount,
                    'is_tax_inclusive' => $item->is_tax_inclusive,
                    'line_total' => $item->line_total,
                    'posted_at' => $item->posted_at?->toDateTimeString(),
                    'posted_by' => $item->postedBy?->only(['id', 'name']),
                ]),
                'payments' => $folio->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'method' => $payment->method->value,
                    'method_label' => $payment->method->label(),
                    'reference_no' => $payment->reference_no,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'received_by' => $payment->receivedBy?->only(['id', 'name']),
                    'is_refund' => $payment->is_refund,
                ]),
            ],
            'balance' => $balance,
            'charges_total' => $chargesTotal,
            'payments_total' => $paymentsTotal,
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
            'canPostPayment' => request()->user()?->can('billing.payment') && $folio->status === FolioStatus::Open,
            'canPostCharge' => request()->user()?->can('billing.post') && $folio->status === FolioStatus::Open,
            'canViewInvoice' => request()->user()?->can('billing.invoice') ?? false,
            'canViewGuestInvoice' => request()->user()?->can('billing.view') ?? false,
            'miscChargeTaxRules' => $taxCalculator->activeRulesPayloadForItemType(FolioItemType::Misc->value),
            'divePackages' => DivePackage::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'price_per_person'])
                ->map(fn (DivePackage $package) => [
                    'id' => $package->id,
                    'code' => $package->code,
                    'name' => $package->name,
                    'type' => $package->type->value,
                    'price_per_person' => (float) $package->price_per_person,
                ]),
            'diveBoatRoutes' => DiveRateItem::boatRoutesPayload(),
            'dailyTripDestinations' => DiveRateItem::dailyTripDestinationsPayload(),
            'dailyTripRentals' => DiveRateItem::dailyTripRentalsPayload(),
            'dailyTripGuide' => DiveRateItem::dailyTripGuidePayload(),
            'revenueCategories' => RevenueCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (RevenueCategory $category) => [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                ]),
        ]);
    }

    public function postCharge(
        Folio $folio,
        PostFolioChargeRequest $request,
        FolioPostingService $folioPostingService,
    ): RedirectResponse {
        $validated = $request->validated();
        $description = $validated['description'];
        $revenueCategoryId = $validated['revenue_category_id'] ?? null;

        $unitPrice = (float) $validated['unit_price'];

        if ($validated['rate_item_id'] ?? null) {
            $rateItem = DiveRateItem::query()->findOrFail($validated['rate_item_id']);

            if (! in_array($rateItem->item_type, [
                DiveRateItemType::DailyTrip,
                DiveRateItemType::DailyTripRental,
                DiveRateItemType::Guide,
            ], true)) {
                return back()->with('error', 'Selected rate item cannot be posted as a daily trip charge.');
            }

            $description = $rateItem->name;
            $unitPrice = (float) $rateItem->price;
        }

        if ($validated['dive_package_id'] ?? null) {
            $divePackage = DivePackage::query()->findOrFail($validated['dive_package_id']);
            $description = 'Dive: '.$divePackage->name;

            if ($divePackage->type === DivePackageType::DivePackage) {
                $boatRate = DiveRateItem::query()->findOrFail($validated['dive_boat_rate_item_id']);
                $description = 'Dive: '.$divePackage->name.' · '.$boatRate->route;
                $quantity = (float) $validated['quantity'];
                $packageLineAmount = (float) $divePackage->price_per_person * $quantity;
                $unitPrice = $quantity > 0
                    ? ($packageLineAmount + (float) $boatRate->price) / $quantity
                    : (float) $divePackage->price_per_person;
            }

            if ($revenueCategoryId === null) {
                $revenueCategoryId = RevenueCategory::query()
                    ->where('hotel_id', $folio->hotel_id)
                    ->where('code', 'dive_center')
                    ->value('id');
            }
        }

        try {
            $folioPostingService->postCharge(
                folio: $folio,
                itemType: FolioItemType::Misc->value,
                description: $description,
                amount: $unitPrice,
                quantity: (float) $validated['quantity'],
                referenceType: 'manual_charge',
                referenceId: null,
                postedBy: $request->user(),
                applyTax: true,
                revenueCategoryId: $revenueCategoryId,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Charge posted to folio successfully.');
    }

    public function postPayment(
        Folio $folio,
        PostFolioPaymentRequest $request,
        FolioPostingService $folioPostingService,
    ): RedirectResponse {
        $folioPostingService->postPayment(
            $folio,
            (float) $request->validated('amount'),
            $request->validated('method'),
            $request->validated('reference_no'),
            $request->user(),
        );

        return back()->with('success', 'Payment recorded successfully.');
    }
}
