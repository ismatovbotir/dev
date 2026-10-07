@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    <x-page-header title="Settings" subtitle="Your account and the Telegram bot connection." />

    <div class="grid items-start gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="user" /></span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Profile</h2>
                    <p class="text-sm text-slate-500">Name and email used to sign in.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.settings.profile') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf @method('PATCH')
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium text-slate-900">Name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-900">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <button class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Save profile</button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="key" /></span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Password</h2>
                    <p class="text-sm text-slate-500">Running <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">php artisan db:seed</code> resets the account to the values in <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">.env</code>.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.settings.password') }}" class="grid gap-4 sm:grid-cols-3">
                @csrf @method('PUT')
                <div>
                    <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-900">Current password</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                    @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-900">New password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-900">Confirm</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                </div>
                <div class="sm:col-span-3">
                    <button class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Update password</button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="bot" /></span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Telegram bot</h2>
                    <p class="text-sm text-slate-500">Configured in <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">.env</code>. Secrets are never shown here.</p>
                </div>
            </div>

            <dl class="divide-y divide-slate-100 text-sm">
                @foreach ($bot as $row)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-slate-500">{{ $row['label'] }}</dt>
                        <dd class="flex items-center gap-2 text-right font-medium text-slate-900">
                            @isset($row['ok'])
                                <x-icon :name="$row['ok'] ? 'check-circle' : 'x-circle'" class="size-4 {{ $row['ok'] ? 'text-slate-900' : 'text-slate-400' }}" />
                            @endisset
                            <span class="break-all">{{ $row['value'] }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" data-webhook-action data-url="{{ route('admin.settings.webhook.set') }}" data-method="POST" data-title="Setting webhook…"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
                    <x-icon name="send" class="size-4" /> Set webhook
                </button>
                <button type="button" data-webhook-action data-url="{{ route('admin.settings.webhook.info') }}" data-method="POST" data-title="Checking connection…"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <x-icon name="refresh" class="size-4" /> Webhook info
                </button>
                <button type="button" data-webhook-action data-url="{{ route('admin.settings.webhook.remove') }}" data-method="DELETE" data-title="Removing webhook…"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <x-icon name="trash" class="size-4" /> Remove webhook
                </button>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="file" /></span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Floor plan examples</h2>
                    <p class="text-sm text-slate-500">Sample images the bot sends, as one album, just before it asks the client for their plan. Up to {{ \App\Models\Setting::PLAN_EXAMPLE_LIMIT }} images.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.settings.plan-examples') }}" enctype="multipart/form-data">
                @csrf

                @if ($planExamples !== [])
                    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ($planExamples as $id => $example)
                            <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <img src="{{ route('admin.settings.plan-example', $id) }}" alt="{{ $example['name'] }}" title="{{ $example['name'] }}" class="aspect-[4/3] w-full bg-white object-contain">
                                <label class="flex items-center gap-2 border-t border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-600">
                                    <input type="checkbox" name="remove[]" value="{{ $id }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600">
                                    <span class="truncate">Remove · {{ $example['name'] }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif

                <label for="examples" class="mb-1.5 block text-sm font-medium text-slate-900">Add images</label>
                <input id="examples" name="examples[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp" data-multi-image-input
                       class="block w-full rounded-lg border border-slate-300 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                <p class="mt-1.5 text-xs text-slate-500">Select several images at once. JPG, PNG or WEBP, up to 5 MB each. {{ count($planExamples) }} of {{ \App\Models\Setting::PLAN_EXAMPLE_LIMIT }} in use.</p>
                @foreach (collect($errors->get('examples'))->merge(collect($errors->get('examples.*'))->flatten())->flatten()->unique() as $message)
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @endforeach

                <div data-multi-preview class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"></div>

                <button class="mt-6 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Save examples</button>
            </form>
        </section>
    </div>

    <dialog id="webhook-modal" aria-labelledby="webhook-modal-title" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                    <x-icon name="refresh" data-modal-icon="loading" class="size-5 animate-spin" />
                    <x-icon name="check-circle" data-modal-icon="ok" class="hidden size-5" />
                    <x-icon name="alert" data-modal-icon="error" class="hidden size-5 text-red-600" />
                </span>
                <h3 id="webhook-modal-title" data-modal-title class="text-base font-semibold text-slate-900">Telegram</h3>
            </div>
            <button type="button" data-modal-close class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-900">
                <x-icon name="x" class="size-5" /><span class="sr-only">Close</span>
            </button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto px-6 py-5">
            <p data-modal-loading class="text-sm text-slate-500">Contacting Telegram…</p>
            <div data-modal-result class="hidden">
                <p data-modal-message class="text-sm text-slate-700"></p>
                <p data-modal-hint class="mt-3 hidden rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600"></p>
                <dl data-modal-rows class="mt-4 divide-y divide-slate-100 text-sm"></dl>
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-200 px-6 py-3">
            <button type="button" data-modal-close class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
        </div>
    </dialog>
@endsection