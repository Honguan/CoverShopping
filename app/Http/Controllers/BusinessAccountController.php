<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitBusinessProfileRequest;
use App\Models\Notification;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class BusinessAccountController extends Controller
{
    public function showBusinessProfileForm(Request $request)
    {
        return view('account.business-profile', [
            'profile' => $request->user()->businessProfile,
        ]);
    }

    public function submitBusinessProfile(SubmitBusinessProfileRequest $request, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $profile = $request->user()->businessProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data + ['status' => 'pending']
        );

        $request->user()->update(['account_type' => 'b2b']);

        User::where('role', 'admin')->get()->each(function (User $admin) use ($profile) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'business_profile_pending',
                'title' => 'ui.notification_business_profile_pending',
                'body' => $profile->company_name,
                'url' => route('admin.dashboard'),
            ]);
        });

        $auditLogService->writeLog('business_profile.submitted', $profile, $data, $request);

        return redirect()->route('business_profile.edit')->with('status', __('ui.business_profile_submitted'));
    }
}
