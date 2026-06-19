<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskProductQuestionRequest;
use App\Models\Product;
use App\Services\ProductQuestionService;

class ProductQuestionController extends Controller
{
    public function askProductQuestion(AskProductQuestionRequest $request, Product $product, ProductQuestionService $questions)
    {
        abort_unless($product->status === 'active', 404);

        $questions->ask($request->user(), $product, $request->validated('question'), $request);

        return back()->with('status', 'Question submitted.');
    }
}
