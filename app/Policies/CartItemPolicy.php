<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;

class CartItemPolicy
{
    public function manage(?User $user, CartItem $cartItem): bool
    {
        if ($user) {
            return $cartItem->user_id === $user->id;
        }

        return $cartItem->session_id === request()->session()->getId();
    }
}
