<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestOrderReturnRequest;
use App\Models\Order;
use App\Services\AuditLogService;

class ReturnRequestController extends Controller
{
    public function requestOrderReturn(RequestOrderReturnRequest $request, Order $order, AuditLogService $auditLogService)
    {
        $this->authorize('requestReturn', $order);

        $returnRequest = $order->returnRequests()->create([
            'user_id' => $request->user()->id,
            'reason' => $request->validated('reason'),
            'status' => 'requested',
        ]);

        $order->update(['return_status' => 'requested']);
        $auditLogService->writeLog('return.requested', $returnRequest, ['order' => $order->number], $request);

        return back()->with('status', '退貨申請已送出');
    }
}
