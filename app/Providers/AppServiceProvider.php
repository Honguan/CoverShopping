<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination');

        $resetUrl = fn (User $user, string $token): string => rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false);
        ResetPassword::createUrlUsing($resetUrl);
        ResetPassword::toMailUsing(fn (User $user, string $token): MailMessage => (new MailMessage)
            ->subject(__('passwords.mail_subject'))
            ->greeting(__('passwords.mail_greeting'))
            ->line(__('passwords.mail_intro'))
            ->action(__('passwords.reset_title'), $resetUrl($user, $token))
            ->line(__('passwords.mail_expiry', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('passwords.mail_ignore'))
            ->salutation(__('passwords.mail_salutation', ['name' => config('app.name')])));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('account')).'|'.$request->ip()));
        RateLimiter::for('product-question', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    }
}
