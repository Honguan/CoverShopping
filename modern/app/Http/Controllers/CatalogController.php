<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->active()->with(['primaryImage', 'category']);

        if ($request->filled('q')) {
            $keyword = trim((string) $request->string('q'));
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('category')) {
            $slug = (string) $request->string('category');
            $query->whereHas('category', fn ($builder) => $builder->where('slug', $slug));
        }

        return view('catalog.index', [
            'products' => $query->latest()->paginate(24)->withQueryString(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($product->status === 'active', 404);

        DB::table('recently_viewed_products')->updateOrInsert(
            [
                'user_id' => $request->user()?->id,
                'session_id' => $request->user() ? null : $request->session()->getId(),
                'product_id' => $product->id,
            ],
            ['viewed_at' => now()]
        );

        return view('catalog.show', [
            'product' => $product->load(['images', 'category', 'seller']),
        ]);
    }
}
