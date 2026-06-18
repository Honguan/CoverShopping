<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function showAddresses(Request $request)
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses()->latest()->get(),
        ]);
    }

    public function storeAddress(StoreAddressRequest $request)
    {
        $data = $request->validated();

        if ($request->boolean('is_default')) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $request->user()->addresses()->create($data + [
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('addresses.index')->with('status', '收件地址已新增');
    }

    public function setDefaultAddress(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->route('addresses.index')->with('status', '預設地址已更新');
    }

    public function deleteAddress(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->delete();

        return redirect()->route('addresses.index')->with('status', '收件地址已刪除');
    }
}
