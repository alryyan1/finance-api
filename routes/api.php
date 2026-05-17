<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CashVoucherController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [LoginController::class, 'user']);
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('fiscal-years',                                    [FiscalYearController::class, 'index']);
    Route::post('fiscal-years',                                   [FiscalYearController::class, 'store']);
    Route::post('fiscal-years/bulk-months',                       [FiscalYearController::class, 'bulkMonths']);
    Route::get('fiscal-years/check-date',                         [FiscalYearController::class, 'checkDate']);
    Route::post('fiscal-years/{fiscal_year}/close',               [FiscalYearController::class, 'close']);
    Route::post('fiscal-years/{fiscal_year}/reopen',              [FiscalYearController::class, 'reopen']);
    Route::post('fiscal-years/{fiscal_year}/carry-forward',       [FiscalYearController::class, 'carryForwardManual']);
    Route::post('settings/logo',   [SettingController::class,       'uploadLogo']);
    Route::delete('settings/logo', [SettingController::class,       'deleteLogo']);
    Route::get('settings',         [SettingController::class,       'index']);
    Route::put('settings',         [SettingController::class,       'update']);
    Route::get('opening-balances', [OpeningBalanceController::class,'index']);
    Route::put('opening-balances', [OpeningBalanceController::class,'update']);
    Route::get('reports/trial-balance',        [ReportController::class, 'trialBalance']);
    Route::get('reports/trial-balance/pdf',    [ReportController::class, 'trialBalancePdf']);
    Route::get('reports/ledger',               [ReportController::class, 'ledger']);
    Route::get('reports/ledger/pdf',           [ReportController::class, 'ledgerPdf']);
    Route::get('reports/income-statement',     [ReportController::class, 'incomeStatement']);
    Route::get('reports/income-statement/pdf', [ReportController::class, 'incomeStatementPdf']);
    Route::get('reports/balance-sheet',        [ReportController::class, 'balanceSheet']);
    Route::get('reports/balance-sheet/pdf',    [ReportController::class, 'balanceSheetPdf']);
    Route::get('cash-vouchers',                                [CashVoucherController::class, 'index']);
    Route::post('cash-vouchers',                               [CashVoucherController::class, 'store']);
    Route::delete('cash-vouchers/{cashVoucher}',               [CashVoucherController::class, 'destroy']);
    Route::get('cash-vouchers/{cashVoucher}/voucher',          [CashVoucherController::class, 'voucher']);
    // Petty cash
    Route::get('petty-cash/fund',                                          [PettyCashController::class, 'fund']);
    Route::post('petty-cash/fund',                                         [PettyCashController::class, 'setupFund']);
    Route::get('petty-cash/categories',                                    [PettyCashController::class, 'categories']);
    Route::get('petty-cash/requests',                                      [PettyCashController::class, 'requests']);
    Route::post('petty-cash/requests',                                     [PettyCashController::class, 'storeRequest']);
    Route::post('petty-cash/requests/{pettyCashRequest}/approve',          [PettyCashController::class, 'approveRequest']);
    Route::post('petty-cash/requests/{pettyCashRequest}/reject',           [PettyCashController::class, 'rejectRequest']);
    Route::post('petty-cash/requests/{pettyCashRequest}/pay',              [PettyCashController::class, 'payRequest']);
    Route::get('petty-cash/requests/{pettyCashRequest}/document',          [PettyCashController::class, 'viewDocument']);
    Route::get('petty-cash/replenishments',                                [PettyCashController::class, 'replenishments']);
    Route::post('petty-cash/replenishments',                               [PettyCashController::class, 'storeReplenishment']);
    Route::post('petty-cash/replenishments/{replenishment}/approve',       [PettyCashController::class, 'approveReplenishment']);
    Route::post('petty-cash/replenishments/{replenishment}/reject',        [PettyCashController::class, 'rejectReplenishment']);

    Route::get('users/roles', [UserController::class, 'roles']);
    Route::apiResource('users', UserController::class)->except(['show']);
    // Backup
    Route::get('backup',                          [BackupController::class, 'index']);
    Route::post('backup/run',                     [BackupController::class, 'run']);
    Route::get('backup/download/{filename}',      [BackupController::class, 'download']);
    Route::delete('backup/{filename}',            [BackupController::class, 'destroy']);

    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('parties', PartyController::class);
    Route::apiResource('journal-entries', JournalEntryController::class);
    Route::patch('journal-entries/{journal_entry}/post',    [JournalEntryController::class, 'post']);
    Route::post('journal-entries/{journal_entry}/reverse',  [JournalEntryController::class, 'reverse']);
    Route::get('journal-entries/{journal_entry}/voucher',   [JournalEntryController::class, 'voucher']);
});
