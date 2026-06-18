<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function requestReturn(User $user, Order $order): bool
    {
        return $order->user_id === $user->id
            && $order->return_status === 'none'
            && in_array($order->fulfillment_status, ['shipped', 'completed'], true);
    }
}
