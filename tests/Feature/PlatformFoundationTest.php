<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    public function test_public_pages_include_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
    }

    public function test_visitor_can_switch_to_a_supported_locale(): void
    {
        $this->get('/locale/ja')->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertSee('lang="ja"', false);
    }

    public function test_visitor_cannot_select_an_unsupported_locale(): void
    {
        $this->get('/locale/invalid')->assertNotFound();
    }

    public function test_each_supported_locale_translates_global_navigation(): void
    {
        foreach ([
            'zh_TW' => '搜尋',
            'en' => 'Search',
            'ja' => '検索',
            'ko' => '검색',
            'es' => 'Buscar',
        ] as $locale => $searchLabel) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->get('/')->assertSee($searchLabel);
        }
    }

    public function test_production_cache_can_use_redis(): void
    {
        $this->assertSame('redis', config('cache.stores.redis.driver'));
        $this->assertSame('cache', config('cache.stores.redis.connection'));
        $this->assertSame('redis', config('queue.connections.redis.driver'));
    }

    public function test_catalog_caches_active_categories(): void
    {
        Category::create([
            'name' => 'Cached Category',
            'slug' => 'cached-category',
            'is_active' => true,
        ]);

        $this->get('/')->assertOk();

        $this->assertTrue(Cache::has('catalog.active-categories'));
    }
}
