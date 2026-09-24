@php
    $current = $page->currentPage();
    $last = $page->lastPage();
    $numbers = collect([1, 2, $last - 1, $last])->merge(range(max(1, $current - 2), min($last, $current + 2)))->filter(fn ($number) => $number >= 1 && $number <= $last)->unique()->sort()->values();
    $suffix = empty($anchor) ? '' : '#'.$anchor;
    $previous = 0;
@endphp
<nav class="asista-pagination" aria-label="Navigasi halaman">
@if($page->previousPageUrl())<a class="page-arrow" href="{{ $page->previousPageUrl().$suffix }}" aria-label="Halaman sebelumnya">‹</a>@else<span class="page-arrow disabled" aria-disabled="true" aria-label="Halaman sebelumnya">‹</span>@endif
<div class="page-pill">@foreach($numbers as $number)
@if($previous && $number > $previous + 1)<span class="page-gap" aria-hidden="true">…</span>@endif
@if($number === $current)<span class="page-number active" aria-current="page" aria-label="Halaman {{ $number }}">{{ $number }}</span>@else<a class="page-number" href="{{ $page->url($number).$suffix }}" aria-label="Halaman {{ $number }}">{{ $number }}</a>@endif
@php($previous = $number)
@endforeach</div>
@if($page->nextPageUrl())<a class="page-arrow" href="{{ $page->nextPageUrl().$suffix }}" aria-label="Halaman berikutnya">›</a>@else<span class="page-arrow disabled" aria-disabled="true" aria-label="Halaman berikutnya">›</span>@endif
</nav>
