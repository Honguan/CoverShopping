<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Queries\ProductCatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Tests\TestCase;

class ProductCatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_meilisearch_receives_the_catalog_filter_and_sort_contract(): void
    {
        $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);
        $engine = new CapturingSearchEngine;
        $manager = app(EngineManager::class);
        $manager->extend('meilisearch', fn () => $engine);
        $manager->forgetDrivers();
        config(['scout.driver' => 'meilisearch']);

        try {
            app(ProductCatalogQuery::class)->paginate(Request::create('/products', 'GET', [
                'q' => 'running',
                'category' => 'shoes',
                'min_price' => 100,
                'max_price' => 500,
                'sort' => 'price_desc',
            ]));

            $this->assertInstanceOf(Builder::class, $engine->builder);
            $this->assertSame([
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ['field' => 'category_id', 'operator' => '=', 'value' => $category->id],
                ['field' => 'price', 'operator' => '>=', 'value' => 100],
                ['field' => 'price', 'operator' => '<=', 'value' => 500],
            ], $engine->builder->wheres);
            $this->assertSame([['column' => 'price', 'direction' => 'desc']], $engine->builder->orders);
            $this->assertNotNull($engine->builder->queryCallback);
            $this->assertContains('price', config('scout.meilisearch.index-settings.'.Product::class.'.filterableAttributes'));
        } finally {
            config(['scout.driver' => 'database']);
            $manager->forgetDrivers();
        }
    }

    public function test_sqlite_database_search_fallback_preserves_catalog_filters(): void
    {
        $seller = User::create(['name' => 'Seller', 'account' => 'search-seller', 'password' => 'password', 'role' => 'seller', 'status' => 'active']);
        $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $first = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Needle one', 'price' => 150, 'inventory' => 1, 'status' => 'active']);
        $second = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Needle two', 'price' => 250, 'inventory' => 1, 'status' => 'active']);
        Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Needle inactive', 'price' => 200, 'inventory' => 1, 'status' => 'archived']);
        Product::create(['seller_id' => $seller->id, 'category_id' => $otherCategory->id, 'name' => 'Needle other', 'price' => 200, 'inventory' => 1, 'status' => 'active']);

        $results = app(ProductCatalogQuery::class)->paginate(Request::create('/products', 'GET', [
            'q' => 'needle',
            'category' => 'shoes',
            'min_price' => 100,
            'max_price' => 300,
            'sort' => 'price_desc',
        ]));

        $this->assertSame([$second->id, $first->id], collect($results->items())->pluck('id')->all());
    }
}

class CapturingSearchEngine extends NullEngine
{
    public ?Builder $builder = null;

    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->builder = $builder;

        return parent::paginate($builder, $perPage, $page);
    }
}
