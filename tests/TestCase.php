<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @param Authenticatable $user */
    public function actingAs($user, $guard = null)
    {
        $this->withSession(['password_hash_'.($guard ?? config('auth.defaults.guard')) => $user->getAuthPassword()]);

        return parent::actingAs($user, $guard);
    }
}
