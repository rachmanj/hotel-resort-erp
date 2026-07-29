<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HotelCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->toDateString();

        $idr = Currency::query()->updateOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'exchange_rate_to_base' => 1.0000,
                'effective_date' => $today,
                'is_active' => true,
            ]
        );

        $usd = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate_to_base' => 15500.0000,
                'effective_date' => $today,
                'is_active' => true,
            ]
        );

        ExchangeRate::query()->updateOrCreate(
            ['currency_id' => $idr->id, 'effective_date' => $today],
            ['rate_to_base' => 1.0000]
        );

        ExchangeRate::query()->updateOrCreate(
            ['currency_id' => $usd->id, 'effective_date' => $today],
            ['rate_to_base' => 15500.0000]
        );

        $hotel = Hotel::query()->updateOrCreate(
            ['code' => 'PTB'],
            [
                'name' => 'Pratasaba Resort',
                'address' => 'Kawasan ITDC Nusa Dua, Bali',
                'currency' => 'IDR',
                'timezone' => 'Asia/Makassar',
                'default_checkin_time' => '14:00:00',
                'default_checkout_time' => '12:00:00',
                'phone' => '+62 361 771234',
                'email' => 'info@pratasaba-resort.test',
                'is_active' => true,
            ]
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@hotel.test'],
            [
                'name' => 'Admin Hotel',
                'password' => Hash::make('password'),
                'hotel_id' => null,
            ]
        );

        $admin->syncRoles(['admin']);
        $hotel->users()->syncWithoutDetaching([$admin->id]);
    }
}
