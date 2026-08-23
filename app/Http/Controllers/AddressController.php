<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use App\Services\AddressBookService;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function showAddresses(Request $request)
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses()->latest()->get(),
        ]);
    }

    public function storeAddress(StoreAddressRequest $request, AddressBookService $addressBookService)
    {
        $data = $request->validated();
        $isDefault = $request->boolean('is_default');
        unset($data['is_default']);

        $addressBookService->createAddress($request->user(), $data, $isDefault);

        return redirect()->route('addresses.index')->with('status', __('ui.address_saved'));
    }

    public function setDefaultAddress(Request $request, Address $address, AddressBookService $addressBookService)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $addressBookService->setDefaultAddress($request->user(), $address);

        return redirect()->route('addresses.index')->with('status', __('ui.default_address_updated'));
    }

    public function deleteAddress(Request $request, Address $address, AddressBookService $addressBookService)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $addressBookService->deleteAddress($request->user(), $address);

        return redirect()->route('addresses.index')->with('status', __('ui.address_deleted'));
    }
}
