<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy
{
    public function ship(User $user, OrderItem $orderItem): bool
    {
        return ($user->isRole('admin') || $orderItem->seller_id === $user->id)
            && $orderItem->shipping_status === 'pending'
            && $orderItem->order->payment_status === 'paid'
            && in_array($orderItem->order->fulfillment_status, ['processing', 'partially_shipped'], true);
    }

    public function review(User $user, OrderItem $orderItem): bool
    {
        return $orderItem->order->user_id === $user->id
            && in_array($orderItem->order->fulfillment_status, ['shipped', 'completed'], true);
    }
}
