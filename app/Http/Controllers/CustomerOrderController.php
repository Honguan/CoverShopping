<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function showCustomerOrders(Request $request)
    {
        return view('orders.index', [
            'orders' => $request->user()->orders()->with('items')->latest()->paginate(20),
        ]);
    }
}
