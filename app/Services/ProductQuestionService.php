<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Http\Request;

class ProductQuestionService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function ask(User $user, Product $product, string $questionText, ?Request $request = null): ProductQuestion
    {
        $question = $product->questions()->create([
            'user_id' => $user->id,
            'question' => $questionText,
            'status' => 'open',
        ]);

        Notification::create([
            'user_id' => $product->seller_id,
            'type' => 'product_question',
            'title' => 'ui.notification_new_product_question',
            'body' => $product->name,
            'url' => route('seller.products.index'),
        ]);

        $this->auditLogService->writeLog('product.question.created', $question, ['product' => $product->id], $request);

        return $question;
    }
}
