<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
    public function store(Request $request, Order $order, AuditLogger $auditLogger)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_if($order->return_status !== 'none', 409, '此訂單已提出退貨申請');
        abort_unless(in_array($order->fulfillment_status, ['shipped', 'completed'], true), 422);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $returnRequest = $order->returnRequests()->create([
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'status' => 'requested',
        ]);

        $order->update(['return_status' => 'requested']);
        $auditLogger->log('return.requested', $returnRequest, ['order' => $order->number], $request);

        return back()->with('status', '退貨申請已送出');
    }
}
