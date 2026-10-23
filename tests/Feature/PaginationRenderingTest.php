<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationRenderingTest extends TestCase
{
    public function test_pagination_is_localized_and_preserves_independent_page_queries(): void
    {
        foreach (['zh_TW' => ['上一頁', '下一頁'], 'en' => ['Previous', 'Next'], 'ja' => ['前へ', '次へ'], 'ko' => ['이전', '다음'], 'es' => ['Anterior', 'Siguiente']] as $locale => [$previous, $next]) {
            app()->setLocale($locale);
            $html = $this->paginator(2)->appends(['orders_page' => 3])->toHtml();

            $this->assertStringContainsString($previous, $html);
            $this->assertStringContainsString($next, $html);
            $this->assertStringContainsString('users_page=1', $html);
            $this->assertStringContainsString('orders_page=3', $html);
            $this->assertStringContainsString('aria-current="page"', $html);
            $this->assertStringNotContainsString('pagination.', $html);
            $this->assertStringNotContainsString('<svg', $html);
        }
    }

    public function test_first_and_last_pages_disable_only_the_unavailable_direction(): void
    {
        $first = $this->paginator(1)->toHtml();
        $last = $this->paginator(3)->toHtml();

        $this->assertStringNotContainsString('rel="prev"', $first);
        $this->assertStringContainsString('rel="next"', $first);
        $this->assertStringContainsString('rel="prev"', $last);
        $this->assertStringNotContainsString('rel="next"', $last);
        $this->assertStringContainsString('aria-disabled="true"', $first);
        $this->assertStringContainsString('aria-disabled="true"', $last);
    }

    public function test_a_single_page_has_no_pagination_navigation(): void
    {
        $this->assertSame('', trim($this->paginator(1, 1)->toHtml()));
    }

    private function paginator(int $page, int $total = 30): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], $total, 10, $page, [
            'path' => url('/admin/dashboard'), 'pageName' => 'users_page',
        ]);
    }
}
