@extends('layouts.admin')

@section('title', 'Request #'.$shopRequest->id)

@section('content')
    <a href="{{ route('admin.requests.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900">
        <x-icon name="arrow-left" class="size-4" /> All requests
    </a>

    <x-page-header :title="'Request #'.$shopRequest->id" :subtitle="'Created '.$shopRequest->created_at->format('d.m.Y H:i')">
        <x-slot:actions>
            <x-status-badge :status="$shopRequest->status" class="mr-2" />
            <form method="POST" action="{{ route('admin.requests.status', $shopRequest) }}" class="flex gap-2">
                @csrf @method('PATCH')
                <select name="status" aria-label="Status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                    @foreach (\App\Enums\RequestStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($shopRequest->status === $case)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Update</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-base font-semibold text-slate-900">Client details</h2>
            <dl class="space-y-4 text-sm">
                <div class="flex gap-3">
                    <x-icon name="user" class="mt-0.5 size-5 shrink-0 text-slate-400" />
                    <div><dt class="text-xs text-slate-500">Name</dt><dd class="font-medium text-slate-900">{{ $shopRequest->name ?? '—' }}</dd></div>
                </div>
                <div class="flex gap-3">
                    <x-icon name="phone" class="mt-0.5 size-5 shrink-0 text-slate-400" />
                    <div><dt class="text-xs text-slate-500">Phone</dt><dd>
                        @if ($shopRequest->phone)
                            <a class="font-medium text-slate-900 hover:underline" href="tel:{{ $shopRequest->phone }}">{{ $shopRequest->phone }}</a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd></div>
                </div>
                <div class="flex gap-3">
                    <x-icon name="tag" class="mt-0.5 size-5 shrink-0 text-slate-400" />
                    <div><dt class="text-xs text-slate-500">Brand</dt><dd class="font-medium text-slate-900">{{ $shopRequest->brand }}</dd></div>
                </div>
                <div class="flex gap-3">
                    <x-icon name="map-pin" class="mt-0.5 size-5 shrink-0 text-slate-400" />
                    <div>
                        <dt class="text-xs text-slate-500">Location</dt>
                        <dd class="font-medium text-slate-900">{{ $shopRequest->location_text }}</dd>
                        @if ($shopRequest->mapUrl())
                            <a class="mt-1 inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 hover:underline" target="_blank" rel="noopener" href="{{ $shopRequest->mapUrl() }}">
                                Open in maps <x-icon name="external-link" class="size-3.5" />
                            </a>
                        @endif
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-icon name="send" class="mt-0.5 size-5 shrink-0 text-slate-400" />
                    <div>
                        <dt class="text-xs text-slate-500">Telegram</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $shopRequest->telegramUser->username ? '@'.$shopRequest->telegramUser->username : $shopRequest->telegramUser->first_name }}
                            <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-600">{{ strtoupper($shopRequest->telegramUser->locale()) }}</span>
                        </dd>
                    </div>
                </div>

                @foreach ($shopRequest->answers ?? [] as $answer)
                    <div class="flex gap-3">
                        <x-icon :name="array_key_exists('file_id', $answer) && $answer['file_id'] ? 'paperclip' : 'list-checks'" class="mt-0.5 size-5 shrink-0 text-slate-400" />
                        <div>
                            <dt class="text-xs text-slate-500">{{ $answer['label'] }}</dt>
                            <dd class="font-medium text-slate-900">
                                @if (! empty($answer['file']))
                                    <a class="inline-flex items-center gap-1.5 text-brand-700 hover:underline" target="_blank" href="{{ route('admin.requests.attachment', [$shopRequest, $loop->index]) }}">
                                        {{ $answer['value'] }} <x-icon name="download" class="size-4" />
                                    </a>
                                @elseif (! empty($answer['file_id']))
                                    <span class="text-amber-700">{{ $answer['value'] }} (could not be downloaded)</span>
                                @else
                                    {{ $answer['value'] }}
                                @endif
                            </dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-base font-semibold text-slate-900">Progress</h2>
            @php
                $timeline = [
                    ['label' => 'Request received', 'at' => $shopRequest->created_at, 'done' => true],
                    ['label' => 'Under review', 'at' => null, 'done' => $shopRequest->status !== \App\Enums\RequestStatus::New],
                    ['label' => 'Ready file delivered', 'at' => $shopRequest->delivered_at, 'done' => $shopRequest->delivered_at !== null],
                ];
            @endphp
            <ol class="space-y-4">
                @foreach ($timeline as $item)
                    <li class="flex items-start gap-3">
                        <x-icon :name="$item['done'] ? 'check-circle' : 'circle'" class="mt-0.5 size-5 shrink-0 {{ $item['done'] ? 'text-brand-600' : 'text-slate-300' }}" />
                        <div class="text-sm">
                            <div class="{{ $item['done'] ? 'font-medium text-slate-900' : 'text-slate-400' }}">{{ $item['label'] }}</div>
                            @if ($item['at'])
                                <div class="text-xs text-slate-500">{{ $item['at']->format('d.m.Y H:i') }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
            @if ($shopRequest->status === \App\Enums\RequestStatus::Cancelled)
                <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-500">This request was cancelled.</p>
            @endif
        </section>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-3">
            <h2 class="mb-1 text-base font-semibold text-slate-900">Reply to client</h2>
            <p class="mb-5 text-sm text-slate-500">Upload the ready file. It is sent to the client in Telegram in their language.</p>

            @if ($shopRequest->drawing_path)
                <div class="mb-5 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-slate-200"><x-icon name="file" /></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <a class="block truncate font-medium text-slate-900 hover:underline" target="_blank" href="{{ route('admin.requests.drawing', $shopRequest) }}">{{ $shopRequest->drawing_name }}</a>
                        @if ($shopRequest->delivered_at)
                            <span class="text-xs text-slate-500">Delivered {{ $shopRequest->delivered_at->format('d.m.Y H:i') }}@if ($shopRequest->answeredBy) by {{ $shopRequest->answeredBy->name }}@endif</span>
                        @else
                            <span class="text-xs text-amber-700">Not delivered yet</span>
                        @endif
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.requests.reply', $shopRequest) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label for="drawing" class="mb-1.5 block text-sm font-medium text-slate-900">Ready file</label>
                    <input id="drawing" name="drawing" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                           class="block w-full rounded-lg border border-slate-300 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    <p class="mt-1.5 text-xs text-slate-500">JPG, PNG, WEBP or PDF, up to 10 MB.@if ($shopRequest->drawing_path) Leave empty to resend the current file.@endif</p>
                    @error('drawing')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="admin_comment" class="mb-1.5 block text-sm font-medium text-slate-900">Comment <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="admin_comment" name="admin_comment" rows="4" maxlength="600"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">{{ old('admin_comment', $shopRequest->admin_comment) }}</textarea>
                    @error('admin_comment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
                    <x-icon :name="$shopRequest->delivered_at ? 'refresh' : 'send'" class="size-4" />
                    {{ $shopRequest->delivered_at ? 'Send again' : 'Send to client' }}
                </button>
            </form>
        </section>
    </div>
@endsection
