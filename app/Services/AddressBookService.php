<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddressBookService
{
    public function createAddress(User $user, array $data, bool $isDefault): Address
    {
        return DB::transaction(function () use ($user, $data, $isDefault): Address {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($isDefault) {
                Address::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            return Address::create($data + [
                'user_id' => $user->id,
                'is_default' => $isDefault,
            ]);
        }, 3);
    }

    public function setDefaultAddress(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $address = Address::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($address->id);

            Address::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        }, 3);
    }

    public function deleteAddress(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $address = Address::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($address->id);
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                Address::query()
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first()
                    ?->update(['is_default' => true]);
            }
        }, 3);
    }
}
