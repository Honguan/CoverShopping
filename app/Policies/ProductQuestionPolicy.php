<?php

namespace App\Policies;

use App\Models\ProductQuestion;
use App\Models\User;

class ProductQuestionPolicy
{
    public function answer(User $user, ProductQuestion $productQuestion): bool
    {
        return $user->isRole('admin') || $productQuestion->product->seller_id === $user->id;
    }
}
