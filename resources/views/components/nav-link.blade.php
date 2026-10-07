@props(['href', 'icon', 'active' => false, 'badge' => 0])

<a href="{{ $href }}" @class([
    'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
    'bg-white/10 text-white' => $active,
    'text-slate-400 hover:bg-white/5 hover:text-white' => ! $active,
]) @if ($active) aria-current="page" @endif>
    @if ($active)
        <span class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brand-500"></span>
    @endif
    <x-icon :name="$icon" class="size-5 shrink-0" />
    <span class="flex-1">{{ $slot }}</span>
    @if ($badge > 0)
        <span class="rounded-full bg-brand-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $badge }}</span>
    @endif
</a>
