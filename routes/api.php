<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExternalPartyMappingController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserJournalAccountController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [LoginController::class, 'user']);
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::put('/user/password', [LoginController::class, 'updatePassword']);

    // Personal access tokens (used by external systems like clinic app)
    Route::get('tokens', [TokenController::class, 'index'])->middleware('permission:tokens.view');
    Route::post('tokens', [TokenController::class, 'store'])->middleware('permission:tokens.create');
    Route::delete('tokens/{id}', [TokenController::class, 'destroy'])->middleware('permission:tokens.revoke');

    // Per-user journal account settings (identified by Bearer token owner) — self-scoped, no extra permission needed
    Route::get('user/journal-accounts', [UserJournalAccountController::class, 'show']);
    Route::put('user/journal-accounts', [UserJournalAccountController::class, 'update']);

    // Generic external-system → finance party mappings (sales-api, clinic app, etc.)
    Route::middleware('permission:parties.link-external')->group(function () {
        Route::get('party-mappings', [ExternalPartyMappingController::class, 'index']);
        Route::put('party-mappings/{source_system}/{source_type}/{source_id}', [ExternalPartyMappingController::class, 'upsert']);
        Route::delete('party-mappings/{source_system}/{source_type}/{source_id}', [ExternalPartyMappingController::class, 'destroy']);
    });

    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view');

    // No permission gate on these two: the fiscal-year list and date-check are
    // read-only, low-sensitivity, and consumed app-wide (Topbar period chip,
    // FiscalYearsProvider wrapping every page, the journal entry form's date
    // validation) — not just the Fiscal Years admin screen. Gating them behind
    // fiscal-years.view broke the topbar/date checks for any role that lacked
    // that specific permission, even users who could otherwise use the app fine.
    Route::get('fiscal-years', [FiscalYearController::class, 'index']);
    Route::post('fiscal-years', [FiscalYearController::class, 'store'])->middleware('permission:fiscal-years.create');
    Route::post('fiscal-years/bulk-months', [FiscalYearController::class, 'bulkMonths'])->middleware('permission:fiscal-years.create');
    Route::get('fiscal-years/check-date', [FiscalYearController::class, 'checkDate']);
    Route::post('fiscal-years/{fiscal_year}/close', [FiscalYearController::class, 'close'])->middleware('permission:fiscal-years.close');
    Route::post('fiscal-years/{fiscal_year}/reopen', [FiscalYearController::class, 'reopen'])->middleware('permission:fiscal-years.reopen');
    Route::post('fiscal-years/{fiscal_year}/carry-forward', [FiscalYearController::class, 'carryForwardManual'])->middleware('permission:fiscal-years.carry-forward');

    Route::post('settings/logo', [SettingController::class, 'uploadLogo'])->middleware('permission:settings.logo.manage');
    Route::delete('settings/logo', [SettingController::class, 'deleteLogo'])->middleware('permission:settings.logo.manage');
    // No permission gate on the read: SettingController::index only ever returns
    // an explicit allowlist of non-secret config (company info, account-id
    // mappings, petty-cash manager/auditor ids) and is consumed app-wide —
    // PettyCashPage, IncomeStatementPage, FirebaseImportDialog — not just the
    // Settings admin screen. Gating it behind settings.view broke those for any
    // role that lacked that one permission. Mutations stay gated below.
    Route::get('settings', [SettingController::class, 'index']);
    Route::put('settings', [SettingController::class, 'update'])->middleware('permission:settings.edit');

    Route::get('opening-balances', [OpeningBalanceController::class, 'index'])->middleware('permission:opening-balances.view');
    Route::put('opening-balances', [OpeningBalanceController::class, 'update'])->middleware('permission:opening-balances.edit');

    // Also usable by transactions.create/.edit: JournalEntryFormPage loads this
    // alongside accounts/parties in one Promise.all to show live account
    // balances while composing an entry — without this OR, a user who can
    // create/edit journal entries but lacks reports.view got a totally broken
    // "New Entry" form (Promise.all fails fast, so accounts/parties never
    // loaded either, not just the balances).
    Route::get('reports/trial-balance', [ReportController::class, 'trialBalance'])->middleware('permission:reports.view|transactions.create|transactions.edit');
    Route::get('reports/trial-balance/pdf', [ReportController::class, 'trialBalancePdf'])->middleware('permission:reports.export');
    Route::get('reports/trial-balance/excel', [ReportController::class, 'trialBalanceExcel'])->middleware('permission:reports.export');
    Route::get('reports/ledger', [ReportController::class, 'ledger'])->middleware('permission:reports.view');
    Route::get('reports/ledger/pdf', [ReportController::class, 'ledgerPdf'])->middleware('permission:reports.export');
    Route::get('reports/income-statement', [ReportController::class, 'incomeStatement'])->middleware('permission:reports.view');
    Route::get('reports/income-statement/pdf', [ReportController::class, 'incomeStatementPdf'])->middleware('permission:reports.export');
    Route::get('reports/income-statement/excel', [ReportController::class, 'incomeStatementExcel'])->middleware('permission:reports.export');
    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet'])->middleware('permission:reports.view');
    Route::get('reports/balance-sheet/pdf', [ReportController::class, 'balanceSheetPdf'])->middleware('permission:reports.export');
    Route::get('reports/balance-sheet/excel', [ReportController::class, 'balanceSheetExcel'])->middleware('permission:reports.export');
    Route::get('reports/balance-sheet/horizontal', [ReportController::class, 'balanceSheetHorizontal'])->middleware('permission:reports.view');
    Route::get('reports/balance-sheet/horizontal/pdf', [ReportController::class, 'balanceSheetHorizontalPdf'])->middleware('permission:reports.export');
    Route::get('reports/balance-sheet/horizontal/excel', [ReportController::class, 'balanceSheetHorizontalExcel'])->middleware('permission:reports.export');
    Route::get('reports/statement-of-equity', [ReportController::class, 'statementOfEquity'])->middleware('permission:reports.view');
    Route::get('reports/statement-of-equity/pdf', [ReportController::class, 'statementOfEquityPdf'])->middleware('permission:reports.export');
    Route::get('reports/statement-of-equity/excel', [ReportController::class, 'statementOfEquityExcel'])->middleware('permission:reports.export');

    Route::get('petty-cash/transactions', [PettyCashController::class, 'transactions'])->middleware('permission:petty-cash.view');
    Route::get('petty-cash/transactions/pdf', [PettyCashController::class, 'transactionsPdf'])->middleware('permission:petty-cash.export');
    Route::get('petty-cash/transactions/excel', [PettyCashController::class, 'transactionsExcel'])->middleware('permission:petty-cash.export');
    Route::post('petty-cash/expenses', [PettyCashController::class, 'storeExpense'])->middleware('permission:petty-cash.create');
    Route::post('petty-cash/receipts', [PettyCashController::class, 'storeReceipt'])->middleware('permission:petty-cash.create');
    Route::delete('petty-cash/transactions/{pettyCashTransaction}', [PettyCashController::class, 'destroy'])->middleware('permission:petty-cash.delete');
    Route::get('petty-cash/transactions/{pettyCashTransaction}/document', [PettyCashController::class, 'document'])->middleware('permission:petty-cash.view');
    Route::post('petty-cash/transactions/{pettyCashTransaction}/document', [PettyCashController::class, 'uploadDocument'])->middleware('permission:petty-cash.document.upload');
    Route::delete('petty-cash/transactions/{pettyCashTransaction}/document', [PettyCashController::class, 'deleteDocument'])->middleware('permission:petty-cash.document.delete');
    Route::post('petty-cash/transactions/{pettyCashTransaction}/approve/manager', [PettyCashController::class, 'approveByManager'])->middleware('permission:petty-cash.approve');
    Route::post('petty-cash/transactions/{pettyCashTransaction}/approve/auditor', [PettyCashController::class, 'approveByAuditor'])->middleware('permission:petty-cash.approve.auditor');
    Route::post('petty-cash/transactions/{pettyCashTransaction}/auditor-comment', [PettyCashController::class, 'updateAuditorComment'])->middleware('permission:petty-cash.approve.auditor');
    Route::post('petty-cash/transactions/{pettyCashTransaction}/reconcile', [PettyCashController::class, 'reconcile'])->middleware('permission:petty-cash.reconcile');
    Route::post('petty-cash/transactions/{pettyCashTransaction}/notify', [PettyCashController::class, 'sendNotification'])->middleware('permission:petty-cash.edit');
    Route::post('petty-cash/sync-expense-accounts', [PettyCashController::class, 'syncExpenseAccounts'])->middleware('permission:petty-cash.edit');
    Route::post('petty-cash/import-whatsapp-requests', [PettyCashController::class, 'importWhatsAppRequests'])->middleware('permission:petty-cash.import-whatsapp');
    Route::post('petty-cash/reconcile-pending', [PettyCashController::class, 'reconcilePending'])->middleware('permission:petty-cash.reconcile');

    Route::get('whatsapp/phone-number', [WhatsAppController::class, 'phoneNumber'])->middleware('permission:whatsapp.view');

    Route::get('users/roles', [UserController::class, 'roles'])->middleware('permission:users.view');
    // index also usable by settings.edit: SettingsPage's petty-cash-approval
    // tab needs the user list to assign a manager/auditor, so settings.edit
    // alone previously left that picker blank.
    Route::apiResource('users', UserController::class)->except(['show'])
        ->middlewareFor('index', 'permission:users.view|settings.edit')
        ->middlewareFor('store', 'permission:users.create')
        ->middlewareFor('update', 'permission:users.edit')
        ->middlewareFor('destroy', 'permission:users.delete');

    // Role & permission management
    Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
    Route::apiResource('roles', RoleController::class)->except(['show'])
        ->middlewareFor('index', 'permission:roles.view')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:roles.manage');

    // Backup
    Route::get('backup', [BackupController::class, 'index'])->middleware('permission:backup.view');
    Route::post('backup/run', [BackupController::class, 'run'])->middleware('permission:backup.run');
    Route::get('backup/download/{filename}', [BackupController::class, 'download'])->middleware('permission:backup.download');
    Route::delete('backup/{filename}', [BackupController::class, 'destroy'])->middleware('permission:backup.delete');

    // index/show also accept every other permission whose page fetches the
    // chart of accounts as supporting data (not the point of the page, just
    // a picker/filter it can't render without): petty-cash.* — new
    // expense/receipt account picker (PettyCashPage.tsx); settings.edit —
    // petty-cash accounts + sales-bridge tabs (SettingsPage.tsx); reports.view
    // — ledger account/party filters (LedgerPage.tsx); parties.view — the
    // party-to-account link field (PartiesPage.tsx); transactions.create/.edit
    // — the journal entry form's account picker (JournalEntryFormPage.tsx);
    // fiscal-years.carry-forward — the equity account picker (FiscalYearsPage.tsx).
    // Without these, each of those pages rendered with a silently blank
    // dropdown (or, worse, failed to load anything at all where the fetch was
    // batched in a Promise.all with other calls) despite the user holding the
    // permission that's actually supposed to grant them that page.
    Route::apiResource('accounts', AccountController::class)
        ->middlewareFor(['index', 'show'], 'permission:accounts.view|petty-cash.view|petty-cash.create|petty-cash.edit|settings.edit|reports.view|parties.view|transactions.create|transactions.edit|fiscal-years.carry-forward')
        ->middlewareFor('store', 'permission:accounts.create')
        ->middlewareFor('update', 'permission:accounts.edit')
        ->middlewareFor('destroy', 'permission:accounts.delete');

    Route::post('parties/resolve-external', [PartyController::class, 'resolveExternal'])->middleware('permission:parties.link-external');
    // Same reasoning as accounts above: petty-cash.* (beneficiary picker),
    // reports.view (ledger party filter), transactions.create/.edit (journal
    // entry form's party field).
    Route::apiResource('parties', PartyController::class)
        ->middlewareFor(['index', 'show'], 'permission:parties.view|petty-cash.view|petty-cash.create|petty-cash.edit|reports.view|transactions.create|transactions.edit')
        ->middlewareFor('store', 'permission:parties.create')
        ->middlewareFor('update', 'permission:parties.edit')
        ->middlewareFor('destroy', 'permission:parties.delete');

    Route::get('journal-entries/pdf', [JournalEntryController::class, 'listPdf'])->middleware('permission:transactions.export');
    Route::get('journal-entries/excel', [JournalEntryController::class, 'listExcel'])->middleware('permission:transactions.export');
    Route::apiResource('journal-entries', JournalEntryController::class)
        ->middlewareFor(['index', 'show'], 'permission:transactions.view')
        ->middlewareFor('store', 'permission:transactions.create')
        ->middlewareFor('update', 'permission:transactions.edit')
        ->middlewareFor('destroy', 'permission:transactions.delete');
    Route::patch('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post'])->middleware('permission:transactions.post');
    Route::post('journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse'])->middleware('permission:transactions.reverse');
    Route::get('journal-entries/{journal_entry}/voucher', [JournalEntryController::class, 'voucher'])->middleware('permission:transactions.view');

    // AI
    Route::middleware('permission:ai.use')->group(function () {
        Route::post('ai/chat', [AiController::class, 'chat']);
        Route::post('ai/suggest-description', [AiController::class, 'suggestDescription']);
        Route::post('ai/analyze-report', [AiController::class, 'analyzeReport']);
    });
});
