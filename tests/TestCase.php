<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set to false (e.g. in setUp()) for tests that specifically exercise
     * authorization/permission-denial behavior and need actingAs() to leave
     * the user's roles untouched.
     */
    protected bool $autoGrantAdminRole = true;

    /**
     * Grant the "admin" role (full permissions) to Eloquent users authenticated
     * in tests, so pre-existing tests that only asserted business logic keep
     * exercising it without needing to know about the permission system.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->autoGrantAdminRole && $user instanceof User && ! $user->hasRole('admin')) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $user->assignRole('admin');
        }

        return parent::actingAs($user, $guard);
    }
}
