@props(['name'])

<span {{ $attributes->class(['flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 uppercase']) }}>
    {{ mb_substr(collect(explode(' ', trim($name)))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''), 0, 2) }}
</span>
