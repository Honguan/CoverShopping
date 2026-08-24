<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestOrderReturnRequest;
use App\Models\Order;
use App\Services\ReturnRequestService;

class ReturnRequestController extends Controller
{
    public function requestOrderReturn(RequestOrderReturnRequest $request, Order $order, ReturnRequestService $returns)
    {
        $returns->request($request->user(), $order, $request->validated('reason'), $request);

        return back()->with('status', __('ui.return_requested_message'));
    }
}
