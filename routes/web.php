<?php

use App\Http\Controllers\Accounting\ArInvoiceController;
use App\Http\Controllers\Accounting\BankAccountController;
use App\Http\Controllers\Accounting\BankReconciliationController;
use App\Http\Controllers\Accounting\BudgetController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\FixedAssetController;
use App\Http\Controllers\Accounting\GeneralLedgerController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\Reports\BalanceSheetController;
use App\Http\Controllers\Accounting\Reports\CashFlowController;
use App\Http\Controllers\Accounting\Reports\IncomeStatementController;
use App\Http\Controllers\Accounting\Reports\TrialBalanceController;
use App\Http\Controllers\Accounting\SupplierInvoiceController;
use App\Http\Controllers\Accounting\TaxReportController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AgentRateController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\HotelSettingController;
use App\Http\Controllers\Admin\PromotionCodeController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\RatePlanController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\TaxRuleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AgentPortal\BookingController as AgentPortalBookingController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\FolioController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HotelContextController;
use App\Http\Controllers\HousekeepingAssignmentController;
use App\Http\Controllers\HousekeepingController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KitchenDisplayController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Profile\TelegramLinkController;
use App\Http\Controllers\PromotionQuoteController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\Reports\AdrRevParController;
use App\Http\Controllers\Reports\ConsolidatedReportController;
use App\Http\Controllers\Reports\DailyRevenueController;
use App\Http\Controllers\Reports\FbSalesController;
use App\Http\Controllers\Reports\HousekeepingEfficiencyController;
use App\Http\Controllers\Reports\OccupancyController;
use App\Http\Controllers\ReservationCalendarController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\SpaAppointmentController;
use App\Http\Controllers\SpaTherapistController;
use App\Http\Controllers\SpaTreatmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WorkOrderController;
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
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create')->middleware('can:rooms.manage');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store')->middleware('can:rooms.manage');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show')->middleware('can:rooms.view');
    Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit')->middleware('can:rooms.manage');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update')->middleware('can:rooms.manage');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy')->middleware('can:rooms.manage');

    Route::get('/room-types', [RoomTypeController::class, 'index'])->name('room-types.index')->middleware('can:rooms.manage');
    Route::post('/room-types', [RoomTypeController::class, 'store'])->name('room-types.store')->middleware('can:rooms.manage');
    Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update')->middleware('can:rooms.manage');
    Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy')->middleware('can:rooms.manage');

    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index')->middleware('can:reservations.view');
    Route::get('/reservations/calendar', [ReservationCalendarController::class, 'index'])->name('reservations.calendar')->middleware('can:reservations.view');
    Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create')->middleware('can:reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store')->middleware('can:reservations.create');
    Route::post('/reservations/quote', [PromotionQuoteController::class, 'store'])->name('reservations.quote')->middleware('can:reservations.create');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show')->middleware('can:reservations.view');
    Route::get('/reservations/{reservation}/edit', [ReservationController::class, 'edit'])->name('reservations.edit')->middleware('can:reservations.edit');
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update')->middleware('can:reservations.edit');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel')->middleware('can:reservations.cancel');
    Route::post('/reservations/{reservation}/checkin', [CheckInController::class, 'store'])->name('reservations.checkin')->middleware('can:reservations.checkin');
    Route::post('/reservation-rooms/{reservationRoom}/checkout', [CheckOutController::class, 'store'])->name('reservations.checkout')->middleware('can:reservations.checkout');

    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index')->middleware('can:groups.view');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create')->middleware('can:groups.manage');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store')->middleware('can:groups.manage');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show')->middleware('can:groups.view');
    Route::post('/groups/{group}/reservations', [GroupController::class, 'addReservation'])->name('groups.reservations.add')->middleware('can:groups.manage');
    Route::delete('/groups/{group}/reservations/{reservation}', [GroupController::class, 'removeReservation'])->name('groups.reservations.remove')->middleware('can:groups.manage');
    Route::post('/groups/{group}/checkin', [GroupController::class, 'checkIn'])->name('groups.checkin')->middleware('can:groups.checkin');
    Route::post('/groups/{group}/checkout', [GroupController::class, 'checkOut'])->name('groups.checkout')->middleware('can:groups.checkout');
    Route::post('/groups/{group}/deposit', [GroupController::class, 'storeDeposit'])->name('groups.deposit.store')->middleware('can:groups.manage');
    Route::post('/groups/{group}/invoice/generate', [GroupController::class, 'generateInvoice'])->name('groups.invoice.generate')->middleware('can:billing.invoice');

    Route::get('/folios/{folio}', [FolioController::class, 'show'])->name('folios.show')->middleware('can:folios.view');
    Route::post('/folios/{folio}/payments', [FolioController::class, 'postPayment'])->name('folios.payments.store')->middleware('can:billing.payment');
    Route::get('/folios/{folio}/invoice', [InvoiceController::class, 'show'])->name('folios.invoice')->middleware('can:billing.invoice');
    Route::get('/folios/{folio}/invoice/download', [InvoiceController::class, 'download'])->name('folios.invoice.download')->middleware('can:billing.invoice');

    Route::get('/housekeeping', [HousekeepingController::class, 'index'])->name('housekeeping.index')->middleware('can:housekeeping.view');
    Route::get('/housekeeping/assignments', [HousekeepingAssignmentController::class, 'index'])->name('housekeeping.assignments')->middleware('can:housekeeping.view');
    Route::post('/housekeeping/assignments', [HousekeepingAssignmentController::class, 'store'])->name('housekeeping.assignments.store')->middleware('can:housekeeping.manage');
    Route::put('/housekeeping/assignments/{assignment}', [HousekeepingAssignmentController::class, 'update'])->name('housekeeping.assignments.update')->middleware('can:housekeeping.manage');
    Route::post('/housekeeping/assignments/generate', [HousekeepingAssignmentController::class, 'generate'])->name('housekeeping.assignments.generate')->middleware('can:housekeeping.manage');

    Route::get('/fb/menu', [MenuController::class, 'index'])->name('fb.menu.index')->middleware('can:fb.view');
    Route::post('/fb/menu', [MenuController::class, 'store'])->name('fb.menu.store')->middleware('can:fb.manage');
    Route::put('/fb/menu/{menuItem}', [MenuController::class, 'update'])->name('fb.menu.update')->middleware('can:fb.manage');
    Route::delete('/fb/menu/{menuItem}', [MenuController::class, 'destroy'])->name('fb.menu.destroy')->middleware('can:fb.manage');
    Route::post('/fb/menu/{menuItem}/toggle', [MenuController::class, 'toggleAvailability'])->name('fb.menu.toggle')->middleware('can:fb.manage');
    Route::get('/fb/orders', [OrderController::class, 'index'])->name('fb.orders.index')->middleware('can:fb.view');
    Route::get('/fb/orders/create', [OrderController::class, 'create'])->name('fb.orders.create')->middleware('can:fb.orders.create');
    Route::post('/fb/orders', [OrderController::class, 'store'])->name('fb.orders.store')->middleware('can:fb.orders.create');
    Route::get('/fb/orders/{order}', [OrderController::class, 'show'])->name('fb.orders.show')->middleware('can:fb.view');
    Route::post('/fb/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('fb.orders.cancel')->middleware('can:fb.manage');
    Route::put('/fb/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('fb.orders.status')->middleware('can:fb.manage');
    Route::put('/fb/orders/{order}/items/{item}/status', [OrderController::class, 'updateItemStatus'])->name('fb.orders.items.status');
    Route::post('/fb/orders/{order}/charge-to-room', [OrderController::class, 'chargeToRoom'])->name('fb.orders.charge-to-room')->middleware('can:fb.manage');
    Route::get('/fb/kds', [KitchenDisplayController::class, 'index'])->name('fb.kds')->middleware('can:fb.view');

    Route::get('/spa/treatments', [SpaTreatmentController::class, 'index'])->name('spa.treatments.index')->middleware('can:spa.view');
    Route::post('/spa/treatments', [SpaTreatmentController::class, 'store'])->name('spa.treatments.store')->middleware('can:spa.manage');
    Route::put('/spa/treatments/{spaTreatment}', [SpaTreatmentController::class, 'update'])->name('spa.treatments.update')->middleware('can:spa.manage');
    Route::delete('/spa/treatments/{spaTreatment}', [SpaTreatmentController::class, 'destroy'])->name('spa.treatments.destroy')->middleware('can:spa.manage');

    Route::get('/spa/therapists', [SpaTherapistController::class, 'index'])->name('spa.therapists.index')->middleware('can:spa.view');
    Route::get('/spa/therapists/schedules', [SpaTherapistController::class, 'schedules'])->name('spa.therapists.schedules')->middleware('can:spa.view');
    Route::post('/spa/therapists', [SpaTherapistController::class, 'store'])->name('spa.therapists.store')->middleware('can:spa.manage');
    Route::post('/spa/therapists/schedules', [SpaTherapistController::class, 'storeSchedule'])->name('spa.therapists.schedules.store')->middleware('can:spa.manage');
    Route::put('/spa/therapists/{spaTherapist}', [SpaTherapistController::class, 'update'])->name('spa.therapists.update')->middleware('can:spa.manage');
    Route::delete('/spa/therapists/{spaTherapist}', [SpaTherapistController::class, 'destroy'])->name('spa.therapists.destroy')->middleware('can:spa.manage');
    Route::delete('/spa/therapists/schedules/{schedule}', [SpaTherapistController::class, 'destroySchedule'])->name('spa.therapists.schedules.destroy')->middleware('can:spa.manage');

    Route::get('/spa/appointments', [SpaAppointmentController::class, 'index'])->name('spa.appointments.index')->middleware('can:spa.view');
    Route::get('/spa/appointments/check-availability', [SpaAppointmentController::class, 'checkAvailability'])->name('spa.appointments.check-availability')->middleware('can:spa.view');
    Route::post('/spa/appointments', [SpaAppointmentController::class, 'store'])->name('spa.appointments.store')->middleware('can:spa.manage');
    Route::put('/spa/appointments/{spaAppointment}/status', [SpaAppointmentController::class, 'updateStatus'])->name('spa.appointments.status')->middleware('can:spa.manage');
    Route::post('/spa/appointments/{spaAppointment}/cancel', [SpaAppointmentController::class, 'cancel'])->name('spa.appointments.cancel')->middleware('can:spa.manage');
    Route::post('/spa/appointments/{spaAppointment}/charge', [SpaAppointmentController::class, 'chargeToRoom'])->name('spa.appointments.charge')->middleware('can:spa.manage');

    Route::get('/inventory', [InventoryItemController::class, 'index'])->name('inventory.index')->middleware('can:inventory.view');
    Route::post('/inventory', [InventoryItemController::class, 'store'])->name('inventory.store')->middleware('can:inventory.view');
    Route::put('/inventory/{inventoryItem}', [InventoryItemController::class, 'update'])->name('inventory.update')->middleware('can:inventory.view');
    Route::post('/inventory/{inventoryItem}/adjust', [InventoryItemController::class, 'adjust'])->name('inventory.adjust')->middleware('can:inventory.view');

    Route::get('/purchasing/suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('can:purchasing.view');
    Route::post('/purchasing/suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('can:purchasing.view');
    Route::put('/purchasing/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('can:purchasing.view');

    Route::get('/purchasing/requisitions', [PurchaseRequisitionController::class, 'index'])->name('requisitions.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/requisitions/create', [PurchaseRequisitionController::class, 'create'])->name('requisitions.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/requisitions/{purchaseRequisition}', [PurchaseRequisitionController::class, 'show'])->name('requisitions.show')->middleware('can:purchasing.view');
    Route::post('/purchasing/requisitions', [PurchaseRequisitionController::class, 'store'])->name('requisitions.store')->middleware('can:purchasing.view');
    Route::post('/purchasing/requisitions/{purchaseRequisition}/submit', [PurchaseRequisitionController::class, 'submit'])->name('requisitions.submit')->middleware('can:purchasing.view');
    Route::post('/purchasing/requisitions/{purchaseRequisition}/approve', [PurchaseRequisitionController::class, 'approve'])->name('requisitions.approve')->middleware('can:purchasing.approve');

    Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show')->middleware('can:purchasing.view');
    Route::post('/purchasing/orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store')->middleware('can:purchasing.view');
    Route::post('/purchasing/orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive')->middleware('can:purchasing.view');

    Route::get('/maintenance/requests', [MaintenanceRequestController::class, 'index'])->name('maintenance.index')->middleware('can:maintenance.view');
    Route::post('/maintenance/requests', [MaintenanceRequestController::class, 'store'])->name('maintenance.store')->middleware('can:maintenance.create');
    Route::put('/maintenance/requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'update'])->name('maintenance.update')->middleware('can:maintenance.manage');
    Route::post('/maintenance/requests/{maintenanceRequest}/resolve', [MaintenanceRequestController::class, 'resolve'])->name('maintenance.resolve')->middleware('can:maintenance.manage');

    Route::get('/maintenance/assets', [AssetController::class, 'index'])->name('assets.index')->middleware('can:maintenance.manage');
    Route::post('/maintenance/assets', [AssetController::class, 'store'])->name('assets.store')->middleware('can:maintenance.manage');
    Route::put('/maintenance/assets/{asset}', [AssetController::class, 'update'])->name('assets.update')->middleware('can:maintenance.manage');

    Route::get('/maintenance/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index')->middleware('can:maintenance.view');
    Route::post('/maintenance/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store')->middleware('can:maintenance.manage');
    Route::put('/maintenance/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update')->middleware('can:maintenance.manage');
    Route::post('/maintenance/work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete'])->name('work-orders.complete')->middleware('can:maintenance.manage');

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/daily-revenue', [DailyRevenueController::class, 'index'])->name('daily-revenue')->middleware('can:reports.view');
        Route::get('/occupancy', [OccupancyController::class, 'index'])->name('occupancy')->middleware('can:reports.view');
        Route::get('/adr-revpar', [AdrRevParController::class, 'index'])->name('adr-revpar')->middleware('can:reports.view');
        Route::get('/fb-sales', [FbSalesController::class, 'index'])->name('fb-sales')->middleware('can:reports.fb_sales');
        Route::get('/hk-efficiency', [HousekeepingEfficiencyController::class, 'index'])->name('hk-efficiency')->middleware('can:reports.view');
        Route::get('/consolidated', [ConsolidatedReportController::class, 'index'])->name('consolidated')->middleware('can:accounting.view');
    });

    Route::prefix('accounting')->name('accounting.')->group(function (): void {
        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('coa.index')->middleware('can:accounting.view');
        Route::post('/chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('coa.store')->middleware('can:accounting.manage');
        Route::put('/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->name('coa.update')->middleware('can:accounting.manage');

        Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index')->middleware('can:accounting.view');
        Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])->name('journal-entries.create')->middleware('can:accounting.post');
        Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store')->middleware('can:accounting.post');
        Route::get('/journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->name('journal-entries.show')->middleware('can:accounting.view');
        Route::post('/journal-entries/{journalEntry}/submit', [JournalEntryController::class, 'submit'])->name('journal-entries.submit')->middleware('can:accounting.post');
        Route::post('/journal-entries/{journalEntry}/approve', [JournalEntryController::class, 'approve'])->name('journal-entries.approve')->middleware('can:accounting.approve');

        Route::get('/general-ledger', [GeneralLedgerController::class, 'index'])->name('gl.index')->middleware('can:accounting.view');

        Route::get('/reports/trial-balance', [TrialBalanceController::class, 'index'])->name('reports.trial-balance')->middleware('can:accounting.view');
        Route::get('/reports/balance-sheet', [BalanceSheetController::class, 'index'])->name('reports.balance-sheet')->middleware('can:accounting.view');
        Route::get('/reports/income-statement', [IncomeStatementController::class, 'index'])->name('reports.income-statement')->middleware('can:accounting.view');
        Route::get('/reports/cash-flow', [CashFlowController::class, 'index'])->name('reports.cash-flow')->middleware('can:accounting.view');

        Route::get('/receivables', [ArInvoiceController::class, 'index'])->name('receivables.index')->middleware('can:accounting.view');
        Route::get('/receivables/{arInvoice}', [ArInvoiceController::class, 'show'])->name('receivables.show')->middleware('can:accounting.view');

        Route::get('/payables', [SupplierInvoiceController::class, 'index'])->name('payables.index')->middleware('can:accounting.view');
        Route::get('/payables/create', [SupplierInvoiceController::class, 'create'])->name('payables.create')->middleware('can:accounting.manage');
        Route::post('/payables', [SupplierInvoiceController::class, 'store'])->name('payables.store')->middleware('can:accounting.manage');
        Route::get('/payables/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->name('payables.show')->middleware('can:accounting.view');
        Route::post('/payables/{supplierInvoice}/approve', [SupplierInvoiceController::class, 'approve'])->name('payables.approve')->middleware('can:accounting.approve');

        Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index')->middleware('can:accounting.manage');
        Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store')->middleware('can:accounting.manage');
        Route::put('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update')->middleware('can:accounting.manage');

        Route::get('/bank-reconciliation', [BankReconciliationController::class, 'index'])->name('bank-rec.index')->middleware('can:accounting.manage');
        Route::post('/bank-reconciliation', [BankReconciliationController::class, 'store'])->name('bank-rec.store')->middleware('can:accounting.manage');
        Route::get('/bank-reconciliation/{bankReconciliation}/reconcile', [BankReconciliationController::class, 'reconcile'])->name('bank-rec.reconcile')->middleware('can:accounting.manage');
        Route::post('/bank-reconciliation/{bankReconciliation}/import-lines', [BankReconciliationController::class, 'importLines'])->name('bank-rec.import-lines')->middleware('can:accounting.manage');
        Route::post('/bank-reconciliation/{bankReconciliation}/match', [BankReconciliationController::class, 'match'])->name('bank-rec.match')->middleware('can:accounting.manage');
        Route::post('/bank-reconciliation/{bankReconciliation}/auto-match', [BankReconciliationController::class, 'autoMatch'])->name('bank-rec.auto-match')->middleware('can:accounting.manage');
        Route::post('/bank-reconciliation/{bankReconciliation}/complete', [BankReconciliationController::class, 'complete'])->name('bank-rec.complete')->middleware('can:accounting.manage');

        Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index')->middleware('can:accounting.manage');
        Route::put('/fixed-assets/{asset}', [FixedAssetController::class, 'update'])->name('fixed-assets.update')->middleware('can:accounting.manage');
        Route::post('/fixed-assets/depreciation/run', [FixedAssetController::class, 'runDepreciation'])->name('fixed-assets.depreciation.run')->middleware('can:accounting.post');

        Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index')->middleware('can:accounting.manage');
        Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store')->middleware('can:accounting.manage');
        Route::get('/budgets/actual', [BudgetController::class, 'actual'])->name('budgets.actual')->middleware('can:accounting.view');
        Route::get('/budgets/{budget}/edit', [BudgetController::class, 'edit'])->name('budgets.edit')->middleware('can:accounting.manage');
        Route::put('/budgets/{budget}/lines', [BudgetController::class, 'updateLines'])->name('budgets.lines.update')->middleware('can:accounting.manage');
        Route::post('/budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('budgets.approve')->middleware('can:accounting.approve');

        Route::get('/tax', [TaxReportController::class, 'index'])->name('tax.index')->middleware('can:accounting.view');
        Route::post('/tax/mark-reported', [TaxReportController::class, 'markReported'])->name('tax.mark-reported')->middleware('can:accounting.manage');
    });

    Route::get('/guests', [GuestController::class, 'index'])->name('guests.index')->middleware('can:guests.view');
    Route::get('/guests/create', [GuestController::class, 'create'])->name('guests.create')->middleware('can:guests.create');
    Route::post('/guests', [GuestController::class, 'store'])->name('guests.store')->middleware('can:guests.create');
    Route::get('/guests/{guest}', [GuestController::class, 'show'])->name('guests.show')->middleware('can:guests.view');
    Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit')->middleware('can:guests.edit');
    Route::put('/guests/{guest}', [GuestController::class, 'update'])->name('guests.update')->middleware('can:guests.edit');
    Route::get('/guests/search', [GuestController::class, 'search'])->name('guests.search')->middleware('can:reservations.create');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index')->middleware('can:companies.view');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create')->middleware('can:companies.manage');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store')->middleware('can:companies.manage');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show')->middleware('can:companies.view');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit')->middleware('can:companies.manage');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update')->middleware('can:companies.manage');

    Route::get('/floors', [FloorController::class, 'index'])->name('floors.index')->middleware('can:floors.manage');
    Route::post('/floors', [FloorController::class, 'store'])->name('floors.store')->middleware('can:floors.manage');
    Route::put('/floors/{floor}', [FloorController::class, 'update'])->name('floors.update')->middleware('can:floors.manage');
    Route::delete('/floors/{floor}', [FloorController::class, 'destroy'])->name('floors.destroy')->middleware('can:floors.manage');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('users', UserController::class)->except(['show'])->middleware('can:admin.manage');
        Route::resource('roles', RoleController::class)->except(['create', 'show', 'edit'])->middleware('can:admin.manage');
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('can:admin.manage');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index')->middleware('can:admin.manage');

        Route::get('/hotels', [HotelController::class, 'index'])->name('hotels.index')->middleware('can:hotels.manage');
        Route::get('/hotels/create', [HotelController::class, 'create'])->name('hotels.create')->middleware('can:hotels.manage');
        Route::post('/hotels', [HotelController::class, 'store'])->name('hotels.store')->middleware('can:hotels.manage');
        Route::get('/hotels/{hotel}/edit', [HotelController::class, 'edit'])->name('hotels.edit')->middleware('can:hotels.manage');
        Route::put('/hotels/{hotel}', [HotelController::class, 'update'])->name('hotels.update')->middleware('can:hotels.manage');
        Route::get('/hotels/{hotel}/users', [HotelController::class, 'userAccess'])->name('hotels.users')->middleware('can:hotels.manage');
        Route::post('/hotels/{hotel}/users', [HotelController::class, 'updateUserAccess'])->name('hotels.users.update')->middleware('can:hotels.manage');

        Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index')->middleware('can:currencies.manage');
        Route::post('/currencies/{currency}/exchange-rates', [CurrencyController::class, 'updateRate'])->name('currencies.exchange-rates.store')->middleware('can:currencies.manage');

        Route::get('/rate-plans', [RatePlanController::class, 'index'])->name('rate-plans.index')->middleware('can:rates.manage');
        Route::post('/rate-plans', [RatePlanController::class, 'store'])->name('rate-plans.store')->middleware('can:rates.manage');
        Route::put('/rate-plans/{ratePlan}', [RatePlanController::class, 'update'])->name('rate-plans.update')->middleware('can:rates.manage');
        Route::delete('/rate-plans/{ratePlan}', [RatePlanController::class, 'destroy'])->name('rate-plans.destroy')->middleware('can:rates.manage');

        Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index')->middleware('can:promotions.view');
        Route::get('/promotions/{promotion}', [PromotionController::class, 'show'])->name('promotions.show')->middleware('can:promotions.view');
        Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store')->middleware('can:promotions.manage');
        Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update')->middleware('can:promotions.manage');
        Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy')->middleware('can:promotions.manage');
        Route::get('/promotions/{promotion}/codes', [PromotionCodeController::class, 'index'])->name('promotions.codes.index')->middleware('can:promotions.view');
        Route::post('/promotions/{promotion}/codes', [PromotionCodeController::class, 'store'])->name('promotions.codes.store')->middleware('can:promotions.manage');
        Route::delete('/promotions/codes/{code}', [PromotionCodeController::class, 'destroy'])->name('promotions.codes.destroy')->middleware('can:promotions.manage');

        Route::get('/seasons', [SeasonController::class, 'index'])->name('seasons.index')->middleware('can:seasons.manage');
        Route::post('/seasons', [SeasonController::class, 'store'])->name('seasons.store')->middleware('can:seasons.manage');
        Route::put('/seasons/{season}', [SeasonController::class, 'update'])->name('seasons.update')->middleware('can:seasons.manage');
        Route::delete('/seasons/{season}', [SeasonController::class, 'destroy'])->name('seasons.destroy')->middleware('can:seasons.manage');

        Route::get('/tax-rules', [TaxRuleController::class, 'index'])->name('tax-rules.index')->middleware('can:tax.manage');
        Route::put('/tax-rules/{taxRule}', [TaxRuleController::class, 'update'])->name('tax-rules.update')->middleware('can:tax.manage');

        Route::get('/hotel-settings', [HotelSettingController::class, 'edit'])->name('hotel-settings.edit')->middleware('can:admin.manage');
        Route::put('/hotel-settings', [HotelSettingController::class, 'update'])->name('hotel-settings.update')->middleware('can:admin.manage');

        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index')->middleware('can:agents.view');
        Route::post('/agents', [AgentController::class, 'store'])->name('agents.store')->middleware('can:agents.manage');
        Route::put('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update')->middleware('can:agents.manage');
        Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy')->middleware('can:agents.manage');
        Route::get('/agents/{agent}/rates', [AgentRateController::class, 'index'])->name('agents.rates.index')->middleware('can:agents.manage');
        Route::post('/agents/{agent}/rates', [AgentRateController::class, 'store'])->name('agents.rates.store')->middleware('can:agents.manage');
        Route::put('/agents/rates/{rate}', [AgentRateController::class, 'update'])->name('agents.rates.update')->middleware('can:agents.manage');
        Route::delete('/agents/rates/{rate}', [AgentRateController::class, 'destroy'])->name('agents.rates.destroy')->middleware('can:agents.manage');
    });

    Route::prefix('agent-portal')->name('agent.')->middleware('agent.portal')->group(function (): void {
        Route::get('/bookings', [AgentPortalBookingController::class, 'index'])->name('bookings.index');
    });

    Route::post('/hotel-context/switch', [HotelContextController::class, 'switch'])->name('hotel-context.switch');

    Route::get('/profile/telegram', [TelegramLinkController::class, 'show'])
        ->name('profile.telegram')
        ->middleware('can:profile.telegram.view');
    Route::post('/profile/telegram/generate-code', [TelegramLinkController::class, 'generate'])
        ->name('profile.telegram.generate')
        ->middleware('can:telegram.link');
});
