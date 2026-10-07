@extends('layouts.admin')

@section('title', 'Dashboard')

@php
    $max = max(1, collect($series)->max('count'));
@endphp

@section('content')
    <x-page-header title="Dashboard" :subtitle="'Welcome back, '.auth()->user()->name.'.'" />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">Total requests</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="inbox" /></span>
            </div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $total }}</div>
        </div>

        <a href="{{ route('admin.requests.index', ['status' => 'new']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-slate-400">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">Awaiting reply</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="clock" /></span>
            </div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $awaitingCount }}</div>
        </a>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">Last 7 days</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="users" /></span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-semibold tracking-tight text-slate-900">{{ $thisWeek }}</span>
                @if ($weekChange !== null)
                    <span class="inline-flex items-center gap-1 text-sm text-slate-500">
                        <x-icon :name="$weekChange >= 0 ? 'trending-up' : 'trending-down'" class="size-4" />
                        {{ $weekChange >= 0 ? '+' : '' }}{{ $weekChange }}% vs prior week
                    </span>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">Avg. response time</span>
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="send" /></span>
            </div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $averageResponse ?? '—' }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="text-base font-semibold text-slate-900">New requests per day</h2>
            <p class="mb-6 text-sm text-slate-500">Last 14 days</p>

            <div class="flex h-48 items-end gap-1.5 border-b border-slate-200" role="img" aria-label="Bar chart of new requests per day over the last 14 days">
                @foreach ($series as $point)
                    <div class="group relative flex h-full flex-1 items-end justify-center" tabindex="0">
                        <div class="w-full max-w-7 rounded-t-md bg-brand-600 transition-colors group-hover:bg-brand-700 group-focus:bg-brand-700"
                             style="height: {{ $point['count'] === 0 ? 0 : max(4, round($point['count'] / $max * 100)) }}%"></div>
                        <div class="pointer-events-none absolute bottom-full z-10 mb-2 hidden -translate-y-0 rounded-md bg-slate-900 px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-lg group-hover:block group-focus:block">
                            <div class="text-slate-300">{{ $point['date']->format('D, d M') }}</div>
                            <div class="font-semibold">{{ $point['count'] }} {{ \Illuminate\Support\Str::plural('request', $point['count']) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex gap-1.5 text-[11px] text-slate-400">
                @foreach ($series as $point)
                    <div class="flex-1 text-center">{{ $loop->odd ? $point['date']->format('d.m') : '' }}</div>
                @endforeach
            </div>

            <details class="mt-4 text-sm text-slate-500">
                <summary class="cursor-pointer hover:text-slate-900">View as table</summary>
                <table class="mt-2 w-full text-left">
                    <thead><tr><th class="py-1 font-medium">Day</th><th class="py-1 font-medium">Requests</th></tr></thead>
                    <tbody>
                        @foreach (array_reverse($series) as $point)
                            <tr class="border-t border-slate-100"><td class="py-1">{{ $point['date']->format('d.m.Y') }}</td><td class="py-1">{{ $point['count'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Needs a reply</h2>
                <a href="{{ route('admin.requests.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-900">All <x-icon name="arrow-right" class="size-4" /></a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($awaiting as $item)
                    <li>
                        <a href="{{ route('admin.requests.show', $item) }}" class="flex items-center gap-3 px-6 py-3 hover:bg-slate-50">
                            <x-avatar :name="$item->name ?? '?'" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-slate-900">{{ $item->name ?? 'Request #'.$item->id }}</div>
                                <div class="truncate text-xs text-slate-500">{{ $item->brand ?? $item->phone }}</div>
                            </div>
                            <span class="shrink-0 text-xs text-slate-400">{{ $item->created_at->diffForHumans(short: true) }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-6 py-12 text-center">
                        <x-icon name="check-circle" class="mx-auto size-8 text-slate-300" />
                        <p class="mt-2 text-sm text-slate-500">You're all caught up.</p>
                    </li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
