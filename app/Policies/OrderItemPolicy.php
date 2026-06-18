<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy
{
    public function ship(User $user, OrderItem $orderItem): bool
    {
        return $user->isRole('admin') || $orderItem->seller_id === $user->id;
    }

    public function review(User $user, OrderItem $orderItem): bool
    {
        return $orderItem->order->user_id === $user->id
            && in_array($orderItem->order->fulfillment_status, ['shipped', 'completed'], true);
    }
}
