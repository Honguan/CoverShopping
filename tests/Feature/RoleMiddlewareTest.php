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

    public function test_suspended_user_cannot_use_an_existing_session(): void
    {
        $user = User::create([
            'name' => 'Suspended Customer',
            'account' => 'suspended-customer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'suspended',
        ]);

        $this->actingAs($user)->get('/orders')->assertRedirect('/login');

        $this->assertGuest();
    }
}
