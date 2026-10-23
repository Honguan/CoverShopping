<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_open_forms_and_authenticated_users_are_redirected(): void
    {
        $this->get(route('login'))->assertSee('href="'.route('password.request').'"', false);
        $this->get(route('password.request'))->assertOk()->assertSee('action="'.route('password.email').'"', false);
        $this->get(route('password.reset', ['token' => 'example-token', 'email' => 'test@example.test']))
            ->assertOk()
            ->assertSee('value="example-token"', false)
            ->assertSee('value="test@example.test"', false)
            ->assertSee('action="'.route('password.update').'"', false);

        $this->actingAs($this->createUser());
        $this->get(route('password.request'))->assertRedirect('/');
        $this->post(route('password.email'))->assertRedirect('/');
        $this->get(route('password.reset', 'token'))->assertRedirect('/');
        $this->post(route('password.update'))->assertRedirect('/');
    }

    public function test_reset_link_is_sent_without_disclosing_whether_the_email_exists(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'))
            ->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            return $this->broker()->tokenExists($user, $notification->token);
        });

        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertSessionHas('status', __('passwords.sent'))
            ->assertSessionHasNoErrors();
        Notification::assertCount(1);
        $this->assertGuest();
    }

    public function test_reset_form_handles_a_non_string_email_query(): void
    {
        $this->get('/reset-password/token?email[]=unexpected')
            ->assertOk()
            ->assertViewHas('email', '');
    }

    public function test_invalid_array_email_does_not_break_forms_after_validation_redirects(): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => ['unexpected']])
            ->assertSessionHasErrors('email');
        $this->get('/forgot-password')->assertOk();
        $this->from('/reset-password/token')->post('/reset-password', [
            'email' => ['unexpected'],
            'token' => 'token',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ])->assertSessionHasErrors('email');
        $this->get('/reset-password/token')->assertOk();
    }

    public function test_valid_reset_changes_only_credentials_and_consumes_the_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->createUser(['role' => 'seller', 'status' => 'suspended']);
        $oldHash = $user->password;
        $token = $this->broker()->createToken($user);
        $payload = $this->resetPayload($user, $token);

        $this->post(route('password.update'), $payload)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('passwords.reset'));

        $user->refresh();
        $this->assertNotSame($oldHash, $user->password);
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertSame(60, strlen($user->remember_token));
        $this->assertSame('seller', $user->role);
        $this->assertSame('suspended', $user->status);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user instanceof User && $event->user->id === $user->id);
        $this->assertGuest();

        $newHash = $user->password;
        $this->post(route('password.update'), $payload)->assertSessionHasErrors(['email' => __('passwords.token')]);
        $this->assertSame($newHash, $user->fresh()->password);
        $this->assertSame(['email' => $user->email], session()->getOldInput());
    }

    public function test_invalid_and_expired_tokens_cannot_change_credentials(): void
    {
        $user = $this->createUser();
        $oldHash = $user->password;
        $this->post(route('password.update'), $this->resetPayload($user, 'invalid-token'))
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $token = $this->broker()->createToken($user);
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->post(route('password.update'), $this->resetPayload($user, $token))
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertSame('old-remember-token', $user->fresh()->remember_token);
        $this->assertSame(['email' => $user->email], session()->getOldInput());
        $this->assertGuest();
    }

    public function test_password_policy_and_confirmation_are_required_without_flashing_secrets(): void
    {
        $user = $this->createUser();
        $token = $this->broker()->createToken($user);
        $oldHash = $user->password;

        foreach (['short1', 'lettersonly', '12345678901', 'ValidPassword123'] as $password) {
            $payload = $this->resetPayload($user, $token);
            $payload['password'] = $password;
            $payload['password_confirmation'] = $password === 'ValidPassword123' ? 'mismatch' : $password;
            $this->post(route('password.update'), $payload)->assertSessionHasErrors('password');
            $this->assertSame(['email' => $user->email], session()->getOldInput());
        }

        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertTrue($this->broker()->tokenExists($user, $token));
    }

    public function test_email_validation_and_required_token_reject_invalid_requests(): void
    {
        Notification::fake();
        foreach (['', 'invalid', str_repeat('a', 150).'@example.test'] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertSessionHasErrors('email');
        }
        $user = $this->createUser();
        $payload = $this->resetPayload($user, 'token');
        unset($payload['token']);
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('token');
        $this->assertSame(['email' => $user->email], session()->getOldInput());
        Notification::assertNothingSent();
    }

    public function test_broker_throttles_repeat_email_requests(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', __('passwords.sent'));
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'))
            ->assertSessionHasNoErrors();
        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertSessionHas('status', __('passwords.sent'))
            ->assertSessionHasNoErrors();
        Notification::assertCount(1);
    }

    public function test_both_post_routes_limit_requests(): void
    {
        foreach (['password.email', 'password.update'] as $name) {
            $this->withServerVariables(['REMOTE_ADDR' => $name === 'password.email' ? '198.51.100.1' : '198.51.100.2']);
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->post(route($name), [])->assertRedirect();
            }
            $this->post(route($name), [])->assertStatus(429);
        }
    }

    public function test_every_locale_translates_forms_and_actual_notification_mail(): void
    {
        $user = $this->createUser(['email' => 'recovery+tag@example.test']);
        config(['app.url' => 'https://shop.example.test']);
        $subcopyKey = "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:";

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $this->withSession(['locale' => $locale]);
            $this->get(route('password.request'))->assertOk()->assertSee(__('passwords.forgot_title', [], $locale));
            $this->get(route('password.reset', 'token'))->assertOk()->assertSee(__('passwords.password_rules', [], $locale));

            $mail = (new ResetPassword('mail-token'))->toMail($user);
            $this->assertSame(__('passwords.mail_subject'), $mail->subject);
            $this->assertSame(__('passwords.reset_title'), $mail->actionText);
            $this->assertSame([__('passwords.mail_intro')], $mail->introLines);
            $this->assertSame([
                __('passwords.mail_expiry', ['minutes' => 60]),
                __('passwords.mail_ignore'),
            ], $mail->outroLines);
            $this->assertSame('https://shop.example.test/reset-password/mail-token?email=recovery%2Btag%40example.test', $mail->actionUrl);
            $rendered = html_entity_decode(strip_tags($mail->render()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            foreach (array_merge($mail->introLines, $mail->outroLines, [$mail->greeting, $mail->actionText, $mail->salutation, __($subcopyKey, ['actionText' => $mail->actionText]), __('All rights reserved.')]) as $text) {
                $this->assertStringContainsString($text, $rendered);
            }
            $this->assertStringNotContainsString($subcopyKey, $rendered);
        }
    }

    public function test_spoofed_host_cannot_change_reset_notification_origin(): void
    {
        Notification::fake();
        config(['app.url' => 'https://shop.example.test/']);
        $user = $this->createUser(['email' => 'recovery+tag@example.test']);

        $this->withServerVariables(['HTTP_HOST' => 'attacker.example.test'])
            ->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $this->assertSame('shop.example.test', parse_url($mail->actionUrl, PHP_URL_HOST));
            $this->assertSame('https', parse_url($mail->actionUrl, PHP_URL_SCHEME));
            parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);
            $this->assertStringContainsString('/reset-password/'.$notification->token, $mail->actionUrl);

            return true;
        });
    }

    public function test_reset_invalidates_a_login_session_before_its_first_redirect_is_followed(): void
    {
        $user = $this->createUser();
        $token = $this->broker()->createToken($user);
        $this->post(route('login.store'), ['account' => $user->account, 'password' => 'OldPassword123'])
            ->assertRedirect(route('catalog.index'))
            ->assertSessionHas('password_hash_web', $user->getAuthPassword());
        $oldSession = session()->all();

        Auth::forgetGuards();
        session()->flush();
        $this->post(route('password.update'), $this->resetPayload($user, $token))->assertRedirect(route('login'));

        Auth::forgetGuards();
        $this->withSession($oldSession)->get(route('orders.index'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['account' => $user->account, 'password' => 'NewPassword456'])
            ->assertRedirect(route('orders.index'));
        $this->get(route('orders.index'))->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_registration_records_the_password_hash_before_its_redirect_is_followed(): void
    {
        $this->post(route('register.store'), [
            'name' => 'New Customer', 'account' => 'new-customer',
            'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456',
        ])->assertRedirect(route('catalog.index'));

        $user = User::where('account', 'new-customer')->firstOrFail();
        $this->assertSame($user->getAuthPassword(), session('password_hash_web'));
        $this->assertAuthenticatedAs($user);
    }

    private function broker(): PasswordBroker
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker('users');

        return $broker;
    }

    private function createUser(array $attributes = []): User
    {
        $user = User::create($attributes + [
            'name' => 'Recovery User',
            'account' => 'recovery-user',
            'email' => 'recovery@example.test',
            'password' => 'OldPassword123',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $user->setRememberToken('old-remember-token');
        $user->save();

        return $user;
    }

    private function resetPayload(User $user, string $token): array
    {
        return [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ];
    }
}
