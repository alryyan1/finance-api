<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [LoginController::class, 'user']);
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('settings',         [SettingController::class,       'index']);
    Route::put('settings',         [SettingController::class,       'update']);
    Route::get('opening-balances', [OpeningBalanceController::class,'index']);
    Route::put('opening-balances', [OpeningBalanceController::class,'update']);
    Route::get('reports/trial-balance',   [ReportController::class, 'trialBalance']);
    Route::get('reports/ledger',          [ReportController::class, 'ledger']);
    Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('reports/balance-sheet',    [ReportController::class, 'balanceSheet']);
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('parties', PartyController::class);
    Route::apiResource('journal-entries', JournalEntryController::class);
    Route::patch('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post']);
});
