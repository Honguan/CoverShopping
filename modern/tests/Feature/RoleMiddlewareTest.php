<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $user = User::create([
            'name' => 'Customer',
            'account' => 'customer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }
}
