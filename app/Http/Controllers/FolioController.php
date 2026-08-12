<?php

namespace App\Http\Controllers;

use App\Enums\FolioStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\PostFolioPaymentRequest;
use App\Models\Folio;
use App\Services\FolioPostingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FolioController extends Controller
{
    public function show(Folio $folio, FolioPostingService $folioPostingService): Response
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
                    'tax_amount' => $item->tax_amount,
                    'service_charge_amount' => $item->service_charge_amount,
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
            'canViewInvoice' => request()->user()?->can('billing.invoice') ?? false,
        ]);
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
