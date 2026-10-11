{{--
    Safely highlights search words inside text.
    <x-highlight :text="$client->display_name" :term="$q" />
    Text is escaped FIRST, then <mark> is added — no XSS risk.
--}}
@props(['text' => '', 'term' => ''])

@php
    $escaped = e((string) $text);
    $words = collect(preg_split('/\s+/', trim((string) $term)))
        ->filter(fn ($w) => mb_strlen($w) >= 2)
        ->map(fn ($w) => preg_quote(e($w), '/'))
        ->unique();

    $html = $words->isEmpty()
        ? $escaped
        : preg_replace('/('.$words->implode('|').')/iu', '<mark class="rounded bg-amber-100 px-0.5 text-inherit">$1</mark>', $escaped);
@endphp
{!! $html !!}
