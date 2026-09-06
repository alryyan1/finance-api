<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'username' => 'admin',
            'email' => 'test@example.com',
        ]);

        $this->call(AccountSeeder::class);
        $this->call(RolesSeeder::class);

        // Sync the admin role to *every* permission that exists (so it never
        // drifts behind permissions added by later migrations) and assign that
        // role to the seeded admin account. Without this the fresh "admin"
        // login has no role at all — permission enforcement locks it out.
        // (The assign_admin_role_to_test_user migration can't do it on a fresh
        // `migrate --seed`: it runs before this seeder creates the user.)
        Artisan::call('permissions:grant-admin', ['--user' => $user->email]);
        $this->command->getOutput()->write(Artisan::output());
    }
}
