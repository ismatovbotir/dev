<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Requests') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-700 antialiased">
    <div id="sidebar-overlay" data-sidebar-toggle class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 items-center gap-3 px-5">
            <span class="flex size-9 items-center justify-center rounded-lg bg-white/10 text-white"><x-icon name="store" /></span>
            <div class="leading-tight">
                <div class="text-sm font-semibold text-white">{{ config('app.name') }}</div>
                <div class="text-xs text-slate-400">Lead management</div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4" aria-label="Main">
            <p class="px-3 pb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">Menu</p>
            <x-nav-link :href="route('admin.dashboard')" icon="layout-dashboard" :active="request()->routeIs('admin.dashboard')">Dashboard</x-nav-link>
            <x-nav-link :href="route('admin.requests.index')" icon="inbox" :active="request()->routeIs('admin.requests.*')" :badge="$newRequestsCount">Requests</x-nav-link>
            <x-nav-link :href="route('admin.clients.index')" icon="users" :active="request()->routeIs('admin.clients.*')">Clients</x-nav-link>

            <p class="px-3 pt-5 pb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">Configuration</p>
            <x-nav-link :href="route('admin.steps.index')" icon="list-checks" :active="request()->routeIs('admin.steps.*')">Bot steps</x-nav-link>
            <x-nav-link :href="route('admin.settings.edit')" icon="settings" :active="request()->routeIs('admin.settings.*')">Settings</x-nav-link>
        </nav>

        <div class="border-t border-white/10 p-3">
            <div class="flex items-center gap-3 rounded-lg px-3 py-2">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white uppercase">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</div>
                    <div class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Log out" class="rounded-md p-1.5 text-slate-400 hover:bg-white/10 hover:text-white">
                        <x-icon name="log-out" class="size-5" />
                        <span class="sr-only">Log out</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:hidden">
            <button type="button" data-sidebar-toggle class="rounded-md p-1.5 text-slate-600 hover:bg-slate-100">
                <x-icon name="menu" />
                <span class="sr-only">Open menu</span>
            </button>
            <span class="font-semibold text-slate-900">{{ config('app.name') }}</span>
        </header>

        <main class="w-full px-4 py-8 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-slate-900" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
