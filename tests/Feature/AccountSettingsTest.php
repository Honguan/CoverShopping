<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->patch(route('notifications.read_all'))->assertRedirect(route('login'));
    }

    public function test_member_updates_profile_but_not_account_or_role(): void
    {
        $user = $this->user('buyer', 'buyer@example.com');
        $this->user('other', 'taken@example.com');

        $this->actingAs($user)->get(route('account.edit'))->assertOk()->assertSee('buyer@example.com');

        $this->from(route('account.edit'))->patch(route('account.update'), ['name' => 'X', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        $this->patch(route('account.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'birthday' => '1990-02-03',
            'account' => 'hijack',
            'role' => 'admin',
        ])->assertRedirect(route('account.edit'));

        $user->refresh();
        $this->assertSame(['New Name', 'new@example.com', 'buyer', 'customer'], [$user->name, $user->email, $user->account, $user->role]);
        $this->assertSame('1990-02-03', Carbon::parse((string) $user->birthday)->toDateString());
    }

    public function test_password_change_requires_current_password_and_keeps_this_session(): void
    {
        $user = $this->user('buyer');

        $this->actingAs($user)->from(route('account.edit'))->put(route('account.password'), [
            'current_password' => 'wrong-password1',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));

        $this->put(route('account.password'), [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('account.edit'));

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->get(route('account.edit'))->assertOk();
    }

    public function test_password_change_signs_out_other_sessions(): void
    {
        $user = $this->user('buyer');
        $this->post(route('login.store'), ['account' => 'buyer', 'password' => 'OldPassword123'])->assertRedirect();
        $otherDevice = session()->all();

        Auth::forgetGuards();
        session()->flush();
        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('account.edit'));

        Auth::forgetGuards();
        $this->withSession($otherDevice)->get(route('account.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_mark_all_read_only_touches_own_unread_notifications(): void
    {
        $user = $this->user('buyer');
        $other = $this->user('other');
        $readAt = now()->subDay()->startOfSecond();
        $mine = Notification::create(['user_id' => $user->id, 'type' => 'order', 'title' => 'A', 'body' => 'B']);
        $alreadyRead = Notification::create(['user_id' => $user->id, 'type' => 'order', 'title' => 'C', 'body' => 'D', 'read_at' => $readAt]);
        $theirs = Notification::create(['user_id' => $other->id, 'type' => 'order', 'title' => 'E', 'body' => 'F']);

        $this->actingAs($user)->get(route('notifications.index'))->assertSee(route('notifications.read_all'), false);
        $this->patch(route('notifications.read_all'))->assertRedirect(route('notifications.index'));

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertEquals($readAt, $alreadyRead->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
        $this->get(route('notifications.index'))->assertDontSee(route('notifications.read_all'), false);
    }

    private function user(string $account, ?string $email = null): User
    {
        return User::create([
            'name' => $account,
            'account' => $account,
            'email' => $email,
            'password' => 'OldPassword123',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }
}
