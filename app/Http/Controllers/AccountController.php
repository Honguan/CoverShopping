<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Requests\UpdateAccountProfileRequest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function showAccount(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(UpdateAccountProfileRequest $request, AuditLogService $auditLogService)
    {
        $data = $request->validated();
        $request->user()->update($data);
        $auditLogService->writeLog('account.profile.updated', $request->user(), $data, $request);

        return redirect()->route('account.edit')->with('status', __('ui.profile_updated'));
    }

    public function updatePassword(UpdateAccountPasswordRequest $request, AuditLogService $auditLogService)
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->setRememberToken(Str::random(60));
        $user->save();

        // AuthenticateSession logs out other sessions; keep this one signed in with the new hash.
        $request->session()->regenerate();
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());
        $auditLogService->writeLog('account.password.updated', $user, [], $request);

        return redirect()->route('account.edit')->with('status', __('ui.password_updated'));
    }
}
