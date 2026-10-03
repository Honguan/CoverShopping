@if ($paginator->hasPages())
  <nav class="pagination" aria-label="{{ __('pagination.label') }}">
    @if ($paginator->onFirstPage())
      <span aria-disabled="true">{{ __('pagination.previous') }}</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('pagination.previous') }}</a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span>{{ $element }}</span>
      @else
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span aria-current="page" aria-label="{{ __('pagination.page', ['page' => $page]) }}">{{ $page }}</span>
          @else
            <a href="{{ $url }}" aria-label="{{ __('pagination.page', ['page' => $page]) }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('pagination.next') }}</a>
    @else
      <span aria-disabled="true">{{ __('pagination.next') }}</span>
    @endif
  </nav>
@endif
