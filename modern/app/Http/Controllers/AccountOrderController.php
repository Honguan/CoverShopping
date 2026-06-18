<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountOrderController extends Controller
{
    public function index(Request $request)
    {
        return view('orders.index', [
            'orders' => $request->user()->orders()->with('items')->latest()->paginate(20),
        ]);
    }
}
