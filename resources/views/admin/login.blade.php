<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-900 px-4 font-sans text-slate-700 antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex flex-col items-center text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-white/10 text-white"><x-icon name="store" class="size-6" /></span>
            <h1 class="mt-4 text-xl font-semibold text-white">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-slate-400">Sign in to manage requests</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-xl">
            @csrf

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-900">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-900">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600"> Remember me
            </label>

            <button class="w-full rounded-lg bg-brand-600 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Sign in</button>
        </form>
    </div>
</body>
</html>
