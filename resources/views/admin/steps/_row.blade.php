<li @class(['flex flex-wrap items-center gap-4 px-4 py-4 sm:px-6', 'bg-slate-50/60' => ! $step->is_active])>
    <div class="flex flex-col">
        <form method="POST" action="{{ route('admin.steps.move', $step) }}">
            @csrf <input type="hidden" name="direction" value="up">
            <button @disabled($first) class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-30 disabled:hover:bg-transparent" title="Move up">
                <x-icon name="chevron-up" class="size-4" /><span class="sr-only">Move up</span>
            </button>
        </form>
        <form method="POST" action="{{ route('admin.steps.move', $step) }}">
            @csrf <input type="hidden" name="direction" value="down">
            <button @disabled($last) class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-30 disabled:hover:bg-transparent" title="Move down">
                <x-icon name="chevron-down" class="size-4" /><span class="sr-only">Move down</span>
            </button>
        </form>
    </div>

    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600">{{ $number }}</span>

    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><x-icon :name="$step->type->icon()" /></span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium text-slate-900">{{ $step->labelFor('en') }}</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $step->type->label() }}</span>
            <span class="text-xs text-slate-500">{{ $step->is_required ? 'Required' : 'Optional' }}</span>
            @if ($step->is_core)
                <span class="text-xs text-slate-400">Built-in</span>
            @endif
        </div>
        <p class="mt-1 truncate text-sm text-slate-500">{{ strip_tags(str_replace("\n", ' · ', $step->questionFor('en'))) }}</p>
        @if ($step->type === \App\Enums\StepType::Choice && $step->optionsFor('en'))
            <p class="mt-1 text-xs text-slate-400">Buttons: {{ implode(' · ', $step->optionsFor('en')) }}</p>
        @endif
    </div>

    <div class="flex items-center gap-1">
        <form method="POST" action="{{ route('admin.steps.toggle', $step) }}">
            @csrf @method('PATCH')
            <button role="switch" aria-checked="{{ $step->is_active ? 'true' : 'false' }}" title="{{ $step->is_active ? 'Disable' : 'Enable' }}"
                    @class(['relative inline-flex h-6 w-11 items-center rounded-full transition-colors', 'bg-brand-600' => $step->is_active, 'bg-slate-300' => ! $step->is_active])>
                <span @class(['inline-block size-4 rounded-full bg-white shadow transition-transform', 'translate-x-6' => $step->is_active, 'translate-x-1' => ! $step->is_active])></span>
                <span class="sr-only">{{ $step->is_active ? 'Disable' : 'Enable' }} step</span>
            </button>
        </form>

        <a href="{{ route('admin.steps.edit', $step) }}" class="ml-2 rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" title="Edit">
            <x-icon name="pencil" class="size-5" /><span class="sr-only">Edit</span>
        </a>

        @unless ($step->is_core)
            <form method="POST" action="{{ route('admin.steps.destroy', $step) }}" onsubmit="return confirm('Delete this step?')">
                @csrf @method('DELETE')
                <button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" title="Delete">
                    <x-icon name="trash" class="size-5" /><span class="sr-only">Delete</span>
                </button>
            </form>
        @endunless
    </div>
</li>
