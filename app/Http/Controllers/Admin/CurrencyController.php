<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Currency\StoreExchangeRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExchangeRateRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(Request $request): Response
    {
        $currencies = Currency::query()
            ->with(['exchangeRates' => fn ($query) => $query->orderByDesc('effective_date')->limit(10)])
            ->orderBy('code')
            ->get();

        return Inertia::render('Admin/Currencies/Index', [
            'currencies' => $currencies,
        ]);
    }

    public function updateRate(StoreExchangeRateRequest $request, Currency $currency, StoreExchangeRate $storeExchangeRate): RedirectResponse
    {
        $storeExchangeRate(
            $currency,
            (float) $request->validated('rate_to_base'),
            $request->validated('effective_date')
        );

        return back()->with('success', 'Exchange rate updated successfully.');
    }
}
