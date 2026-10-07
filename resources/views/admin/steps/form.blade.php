@extends('layouts.admin')

@section('title', $step->exists ? 'Edit step' : 'Add step')

@section('content')
    <a href="{{ route('admin.steps.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900">
        <x-icon name="arrow-left" class="size-4" /> All steps
    </a>

    <x-page-header :title="$step->exists ? 'Edit step' : 'Add step'" subtitle="Wording is shown to the client in their own language. You can use &lt;b&gt;bold&lt;/b&gt; and &lt;i&gt;italic&lt;/i&gt;." />

    <form method="POST" action="{{ $step->exists ? route('admin.steps.update', $step) : route('admin.steps.store') }}" class="space-y-6">
        @csrf
        @if ($step->exists) @method('PUT') @endif

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-slate-900">Behaviour</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="type" class="mb-1.5 block text-sm font-medium text-slate-900">Answer type</label>
                    @if ($step->is_core)
                        <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $step->type->label() }} <span class="text-slate-400">(built-in, cannot change)</span></p>
                    @else
                        <select id="type" name="type" data-step-type class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                            @foreach (\App\Enums\StepType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(old('type', $step->type->value) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @endif
                </div>

                <div>
                    <label for="section" class="mb-1.5 block text-sm font-medium text-slate-900">Block</label>
                    @if ($step->is_core)
                        <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $step->section->label() }} <span class="text-slate-400">(built-in, cannot change)</span></p>
                    @else
                        <select id="section" name="section" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                            @foreach (\App\Enums\StepSection::cases() as $section)
                                <option value="{{ $section->value }}" @selected(old('section', $step->section->value) === $section->value)>{{ $section->label() }} — {{ $section->description() }}</option>
                            @endforeach
                        </select>
                        @error('section')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @endif
                </div>

                <div class="flex flex-col justify-end gap-3 text-sm">
                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $step->is_required)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-600">
                        Required <span class="text-slate-400">(optional steps get a Skip button)</span>
                    </label>
                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $step->is_active)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-600">
                        Active
                    </label>
                </div>
            </div>

            @error('options')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-3">
        @foreach (\App\Enums\Language::cases() as $language)
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900">
                    <x-icon name="globe" class="size-5 text-slate-400" /> {{ $language->label() }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <label for="label_{{ $language->value }}" class="mb-1.5 block text-sm font-medium text-slate-900">Short name <span class="font-normal text-slate-400">(shown in the summary and in the admin)</span></label>
                        <input id="label_{{ $language->value }}" name="label[{{ $language->value }}]" value="{{ old('label.'.$language->value, $step->label[$language->value] ?? '') }}" required maxlength="60"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                        @error('label.'.$language->value)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="question_{{ $language->value }}" class="mb-1.5 block text-sm font-medium text-slate-900">Question</label>
                        <textarea id="question_{{ $language->value }}" name="question[{{ $language->value }}]" rows="3" required maxlength="800"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">{{ old('question.'.$language->value, $step->question[$language->value] ?? '') }}</textarea>
                        @error('question.'.$language->value)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @unless ($step->is_core)
                        <div data-options-field>
                            <label for="options_{{ $language->value }}" class="mb-1.5 block text-sm font-medium text-slate-900">Button options <span class="font-normal text-slate-400">(one per line, same order in every language)</span></label>
                            <textarea id="options_{{ $language->value }}" name="options[{{ $language->value }}]" rows="4"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">{{ old('options.'.$language->value, implode("\n", $step->options[$language->value] ?? [])) }}</textarea>
                            @error('options.'.$language->value)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endunless
                </div>
            </section>
        @endforeach
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700">{{ $step->exists ? 'Save changes' : 'Add step' }}</button>
            <a href="{{ route('admin.steps.index') }}" class="text-sm text-slate-500 hover:text-slate-900">Cancel</a>
        </div>
    </form>
@endsection
