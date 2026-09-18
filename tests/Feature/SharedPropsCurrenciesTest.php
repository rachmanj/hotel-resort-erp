<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SharedPropsCurrenciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'file']);
        Cache::flush();
    }

    public function test_shared_currencies_prop_is_a_cacheable_plain_array(): void
    {
        $today = now()->toDateString();

        Currency::query()->create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'exchange_rate_to_base' => 1.0000,
            'effective_date' => $today,
            'is_active' => true,
        ]);

        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_base' => 15500.0000,
            'effective_date' => $today,
            'is_active' => true,
        ]);

        Currency::query()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'exchange_rate_to_base' => 17000.0000,
            'effective_date' => $today,
            'is_active' => false,
        ]);

        Cache::forget('currencies.active');

        $middleware = app(HandleInertiaRequests::class);
        $shared = $middleware->share(Request::create('/'));
        $resolveCurrencies = $shared['currencies'];

        $resolveCurrencies();
        $currencies = $resolveCurrencies();

        $this->assertIsArray($currencies);
        $this->assertCount(2, $currencies);
        $this->assertStringNotContainsString('__PHP_Incomplete_Class', serialize($currencies));

        foreach ($currencies as $currency) {
            $this->assertIsArray($currency);
            $this->assertArrayHasKey('code', $currency);
            $this->assertArrayHasKey('symbol', $currency);
            $this->assertArrayHasKey('name', $currency);
            $this->assertCount(3, $currency);
        }

        $this->assertSame('IDR', $currencies[0]['code']);
        $this->assertSame('USD', $currencies[1]['code']);
    }
}
