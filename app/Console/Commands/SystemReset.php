<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SystemReset extends Command
{
    protected $signature = 'system:reset
                            {--seed   : Seed a basic Arabic chart of accounts after reset}
                            {--full   : Also delete all users}
                            {--force  : Skip confirmation prompt}';

    protected $description = 'Reset all financial data. Use --full to also reset users, --seed to seed default accounts.';

    private array $financialTables = [
        'journal_entry_lines',
        'cash_vouchers',
        'journal_entries',
        'fiscal_years',
        'opening_balances',
        'parties',
        'accounts',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=red;options=bold>⚠  WARNING: System Reset</>');
        $this->newLine();

        if ($this->option('full')) {
            $this->warn('  Will delete: all financial data + all users');
        } else {
            $this->warn('  Will delete: all financial data (accounts, journal entries, parties, fiscal years...)');
            $this->line('  Will keep: users and company settings');
        }

        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Are you absolutely sure?', false)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  Deleting...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->financialTables as $table) {
            DB::table($table)->truncate();
            $this->line("  <fg=green>✓</> {$table}");
        }

        if ($this->option('full')) {
            DB::table('users')->truncate();
            $this->line('  <fg=green>✓</> users');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        if ($this->option('seed')) {
            $this->newLine();
            $this->line('  Seeding default chart of accounts...');
            $this->seedAccounts();
        }

        $this->newLine();
        $this->info('  ✅ System reset completed successfully.');

        if ($this->option('full')) {
            $this->newLine();
            $this->warn('  Remember: all users have been deleted.');
            $this->line('  Create a new user via:  php artisan tinker');
            $this->line('  >> App\Models\User::create([\'name\'=>\'Admin\',\'email\'=>\'admin@mail.com\',\'password\'=>bcrypt(\'password\')])');
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function seedAccounts(): void
    {
        $now = now();

        $accounts = [
            // ── الأصول ────────────────────────────────────────────
            ['code' => '1',    'name' => 'الأصول',                      'type' => 'asset',     'parent_id' => null],
            ['code' => '11',   'name' => 'الأصول المتداولة',            'type' => 'asset',     'parent_id' => null],
            ['code' => '111',  'name' => 'النقدية وما في حكمها',        'type' => 'asset',     'parent_id' => null],
            ['code' => '1111', 'name' => 'الصندوق',                     'type' => 'asset',     'parent_id' => null],
            ['code' => '1112', 'name' => 'البنك',                       'type' => 'asset',     'parent_id' => null],
            ['code' => '112',  'name' => 'الذمم المدينة',               'type' => 'asset',     'parent_id' => null],
            ['code' => '1121', 'name' => 'العملاء',                     'type' => 'asset',     'parent_id' => null],
            ['code' => '1122', 'name' => 'أوراق القبض',                 'type' => 'asset',     'parent_id' => null],
            ['code' => '113',  'name' => 'المخزون',                     'type' => 'asset',     'parent_id' => null],
            ['code' => '114',  'name' => 'المدفوعات المقدمة',           'type' => 'asset',     'parent_id' => null],
            ['code' => '12',   'name' => 'الأصول الثابتة',              'type' => 'asset',     'parent_id' => null],
            ['code' => '121',  'name' => 'المباني والإنشاءات',          'type' => 'asset',     'parent_id' => null],
            ['code' => '122',  'name' => 'الآلات والمعدات',             'type' => 'asset',     'parent_id' => null],
            ['code' => '123',  'name' => 'السيارات والمركبات',          'type' => 'asset',     'parent_id' => null],
            // ── الخصوم ────────────────────────────────────────────
            ['code' => '2',    'name' => 'الخصوم',                      'type' => 'liability', 'parent_id' => null],
            ['code' => '21',   'name' => 'الخصوم المتداولة',            'type' => 'liability', 'parent_id' => null],
            ['code' => '211',  'name' => 'الذمم الدائنة',               'type' => 'liability', 'parent_id' => null],
            ['code' => '2111', 'name' => 'الموردون',                    'type' => 'liability', 'parent_id' => null],
            ['code' => '2112', 'name' => 'أوراق الدفع',                 'type' => 'liability', 'parent_id' => null],
            ['code' => '212',  'name' => 'المستحقات',                   'type' => 'liability', 'parent_id' => null],
            ['code' => '2121', 'name' => 'رواتب مستحقة',                'type' => 'liability', 'parent_id' => null],
            ['code' => '213',  'name' => 'قروض قصيرة الأجل',           'type' => 'liability', 'parent_id' => null],
            ['code' => '22',   'name' => 'الخصوم طويلة الأجل',         'type' => 'liability', 'parent_id' => null],
            ['code' => '221',  'name' => 'قروض طويلة الأجل',           'type' => 'liability', 'parent_id' => null],
            // ── حقوق الملكية ──────────────────────────────────────
            ['code' => '3',    'name' => 'حقوق الملكية',                'type' => 'equity',    'parent_id' => null],
            ['code' => '31',   'name' => 'رأس المال',                   'type' => 'equity',    'parent_id' => null],
            ['code' => '311',  'name' => 'رأس المال المدفوع',           'type' => 'equity',    'parent_id' => null],
            ['code' => '32',   'name' => 'الاحتياطيات',                 'type' => 'equity',    'parent_id' => null],
            ['code' => '321',  'name' => 'الأرباح المحتجزة',           'type' => 'equity',    'parent_id' => null],
            // ── الإيرادات ─────────────────────────────────────────
            ['code' => '4',    'name' => 'الإيرادات',                   'type' => 'revenue',   'parent_id' => null],
            ['code' => '41',   'name' => 'إيرادات التشغيل',             'type' => 'revenue',   'parent_id' => null],
            ['code' => '411',  'name' => 'إيرادات المبيعات',            'type' => 'revenue',   'parent_id' => null],
            ['code' => '412',  'name' => 'إيرادات الخدمات',             'type' => 'revenue',   'parent_id' => null],
            ['code' => '42',   'name' => 'إيرادات أخرى',               'type' => 'revenue',   'parent_id' => null],
            ['code' => '421',  'name' => 'إيرادات الاستثمار',           'type' => 'revenue',   'parent_id' => null],
            // ── المصروفات ─────────────────────────────────────────
            ['code' => '5',    'name' => 'المصروفات',                   'type' => 'expense',   'parent_id' => null],
            ['code' => '51',   'name' => 'تكلفة المبيعات',              'type' => 'expense',   'parent_id' => null],
            ['code' => '511',  'name' => 'تكلفة البضاعة المباعة',       'type' => 'expense',   'parent_id' => null],
            ['code' => '52',   'name' => 'مصروفات التشغيل',             'type' => 'expense',   'parent_id' => null],
            ['code' => '521',  'name' => 'مصروف الرواتب والأجور',       'type' => 'expense',   'parent_id' => null],
            ['code' => '522',  'name' => 'مصروف الإيجار',               'type' => 'expense',   'parent_id' => null],
            ['code' => '523',  'name' => 'مصروف الكهرباء والمياه',      'type' => 'expense',   'parent_id' => null],
            ['code' => '524',  'name' => 'مصروف الاتصالات',             'type' => 'expense',   'parent_id' => null],
            ['code' => '525',  'name' => 'مصروفات عمومية وإدارية',      'type' => 'expense',   'parent_id' => null],
            ['code' => '53',   'name' => 'مصروفات التمويل',             'type' => 'expense',   'parent_id' => null],
            ['code' => '531',  'name' => 'مصروف الفوائد',               'type' => 'expense',   'parent_id' => null],
        ];

        // Insert without parent_id links first, then wire parents by code
        $codeToId = [];
        foreach ($accounts as $row) {
            $id = DB::table('accounts')->insertGetId([
                'code'       => $row['code'],
                'name'       => $row['name'],
                'type'       => $row['type'],
                'parent_id'  => null,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $codeToId[$row['code']] = $id;
            $this->line("  <fg=green>✓</> {$row['code']} — {$row['name']}");
        }

        // Wire parent_id by matching code prefix
        foreach ($codeToId as $code => $id) {
            if (strlen($code) <= 1) continue;
            // Try longest matching parent: e.g. 1121 → 112 → 11 → 1
            for ($len = strlen($code) - 1; $len >= 1; $len--) {
                $parentCode = substr($code, 0, $len);
                if (isset($codeToId[$parentCode])) {
                    DB::table('accounts')->where('id', $id)->update(['parent_id' => $codeToId[$parentCode]]);
                    break;
                }
            }
        }
    }
}
