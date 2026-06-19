<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReturnRequestService
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function request(User $user, Order $order, string $reason, ?Request $request = null): ReturnRequest
    {
        Gate::forUser($user)->authorize('requestReturn', $order);

        return DB::transaction(function () use ($user, $order, $reason, $request) {
            $returnRequest = $order->returnRequests()->create([
                'user_id' => $user->id,
                'reason' => $reason,
                'status' => 'requested',
            ]);

            $order->update(['return_status' => 'requested']);
            $this->auditLogService->writeLog('return.requested', $returnRequest, ['order' => $order->number], $request);

            return $returnRequest;
        });
    }
}
