<?php

namespace App\Console\Commands;

use Database\Seeders\MedicalAccountSeeder;
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
        app(MedicalAccountSeeder::class)->run();
    }
}
