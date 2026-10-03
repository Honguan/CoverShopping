<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendPasswordResetLinkRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(SendPasswordResetLinkRequest $request)
    {
        $status = Password::broker('users')->sendResetLink($request->validated());

        if (in_array($status, [Password::ResetLinkSent, Password::InvalidUser, Password::ResetThrottled], true)) {
            return back()->with('status', __('passwords.sent'));
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }

    public function showResetPasswordForm(Request $request, string $token)
    {
        $email = $request->query('email', '');

        return view('auth.reset-password', ['token' => $token, 'email' => is_string($email) ? $email : '']);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::broker('users')->reset($request->validated(), function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
            $user->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PasswordReset) {
            return redirect()->route('login')->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
