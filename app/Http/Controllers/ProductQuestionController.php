<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskProductQuestionRequest;
use App\Models\Notification;
use App\Models\Product;
use App\Services\AuditLogService;

class ProductQuestionController extends Controller
{
    public function askProductQuestion(AskProductQuestionRequest $request, Product $product, AuditLogService $auditLogService)
    {
        abort_unless($product->status === 'active', 404);

        $data = $request->validated();

        $question = $product->questions()->create([
            'user_id' => $request->user()->id,
            'question' => $data['question'],
            'status' => 'open',
        ]);

        Notification::create([
            'user_id' => $product->seller_id,
            'type' => 'product_question',
            'title' => 'New product question.',
            'body' => $product->name,
            'url' => route('seller.products.index'),
        ]);

        $auditLogService->writeLog('product.question.created', $question, ['product' => $product->id], $request);

        return back()->with('status', 'Question submitted.');
    }
}
