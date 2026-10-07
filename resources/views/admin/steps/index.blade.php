@extends('layouts.admin')

@section('title', 'Bot steps')

@section('content')
    <x-page-header title="Bot steps" subtitle="Configure what the bot asks. Registration is asked once per client, the conversation for every new request." />

    <div class="space-y-8">
        @foreach (\App\Enums\StepSection::cases() as $section)
            @php $steps = $stepsBySection->get($section->value, collect())->values(); @endphp

            <section>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-slate-900 text-white"><x-icon :name="$section->icon()" /></span>
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">{{ $section->label() }}</h2>
                            <p class="text-sm text-slate-500">{{ $section->description() }}</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.steps.create', ['section' => $section->value]) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
                        <x-icon name="plus" class="size-4" /> Add step
                    </a>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <ul class="divide-y divide-slate-100">
                        @if ($section === \App\Enums\StepSection::Registration)
                            <li class="flex flex-wrap items-center gap-4 bg-slate-50/60 px-4 py-4 sm:px-6">
                                <span class="w-5"></span>
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500"><x-icon name="globe" class="size-4" /></span>
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon name="globe" /></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-900">Language</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Choice buttons</span>
                                        <span class="text-xs text-slate-400">Always first</span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">The client picks O'zbekcha, Русский or English. Everything after that is in their language.</p>
                                </div>
                            </li>
                        @endif

                        @forelse ($steps as $step)
                            @include('admin.steps._row', ['step' => $step, 'first' => $loop->first, 'last' => $loop->last, 'number' => $loop->iteration])
                        @empty
                            <li class="px-6 py-10 text-center text-sm text-slate-500">No steps in this block yet.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        @endforeach
    </div>

    <p class="mt-6 text-sm text-slate-500">Changes apply to new conversations immediately. Disabled steps are skipped. A returning client skips the Registration block and goes straight to the Conversation.</p>
@endsection
