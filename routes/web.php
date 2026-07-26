<?php

use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\HotelContextController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'hotel.context'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index')->middleware('can:rooms.view');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show')->middleware('can:rooms.view');

    Route::get('/room-types', [RoomTypeController::class, 'index'])->name('room-types.index')->middleware('can:rooms.manage');
    Route::post('/room-types', [RoomTypeController::class, 'store'])->name('room-types.store')->middleware('can:rooms.manage');
    Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update')->middleware('can:rooms.manage');
    Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy')->middleware('can:rooms.manage');

    Route::get('/floors', [FloorController::class, 'index'])->name('floors.index')->middleware('can:floors.manage');
    Route::post('/floors', [FloorController::class, 'store'])->name('floors.store')->middleware('can:floors.manage');
    Route::put('/floors/{floor}', [FloorController::class, 'update'])->name('floors.update')->middleware('can:floors.manage');
    Route::delete('/floors/{floor}', [FloorController::class, 'destroy'])->name('floors.destroy')->middleware('can:floors.manage');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/hotels', [HotelController::class, 'index'])->name('hotels.index')->middleware('can:hotels.manage');
        Route::get('/hotels/create', [HotelController::class, 'create'])->name('hotels.create')->middleware('can:hotels.manage');
        Route::post('/hotels', [HotelController::class, 'store'])->name('hotels.store')->middleware('can:hotels.manage');
        Route::get('/hotels/{hotel}/edit', [HotelController::class, 'edit'])->name('hotels.edit')->middleware('can:hotels.manage');
        Route::put('/hotels/{hotel}', [HotelController::class, 'update'])->name('hotels.update')->middleware('can:hotels.manage');
        Route::get('/hotels/{hotel}/users', [HotelController::class, 'userAccess'])->name('hotels.users')->middleware('can:hotels.manage');
        Route::post('/hotels/{hotel}/users', [HotelController::class, 'updateUserAccess'])->name('hotels.users.update')->middleware('can:hotels.manage');

        Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index')->middleware('can:currencies.manage');
        Route::post('/currencies/{currency}/exchange-rates', [CurrencyController::class, 'updateRate'])->name('currencies.exchange-rates.store')->middleware('can:currencies.manage');
    });

    Route::post('/hotel-context/switch', [HotelContextController::class, 'switch'])->name('hotel-context.switch');
});
