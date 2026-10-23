<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_codes_are_normalized_before_unique_validation(): void
    {
        Coupon::create($this->couponData('SAVE10'));
        $this->actingAs($this->createAdmin());

        foreach (['SAVE10', 'save10'] as $code) {
            $this->from('/admin/dashboard')->post('/admin/coupons', $this->couponData($code))
                ->assertRedirect('/admin/dashboard')
                ->assertSessionHasErrors('code');
        }

        $this->assertDatabaseCount('coupons', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_new_lowercase_coupon_code_is_stored_in_uppercase(): void
    {
        $this->actingAs($this->createAdmin())->post('/admin/coupons', $this->couponData('save10'))
            ->assertRedirect('/admin/dashboard')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['code' => 'SAVE10']);
        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_array_coupon_code_returns_a_validation_error(): void
    {
        $data = $this->couponData('SAVE10');
        $data['code'] = ['SAVE10'];

        $this->actingAs($this->createAdmin())->from('/admin/dashboard')->post('/admin/coupons', $data)
            ->assertRedirect('/admin/dashboard')
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('coupons', 0);
    }

    private function couponData(string $code): array
    {
        return ['code' => $code, 'name' => 'Save Ten', 'type' => 'fixed', 'value' => 10];
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'account' => 'admin',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
