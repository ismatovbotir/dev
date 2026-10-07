<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StepSection;
use App\Enums\StepType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegistrationStepRequest;
use App\Models\RegistrationStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationStepController extends Controller
{
    public function index(): View
    {
        return view('admin.steps.index', [
            'stepsBySection' => RegistrationStep::ordered()->get()->groupBy(fn (RegistrationStep $step) => $step->section->value),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.steps.form', [
            'step' => new RegistrationStep([
                'type' => StepType::Text,
                'section' => StepSection::tryFrom((string) $request->query('section')) ?? StepSection::Conversation,
                'is_required' => true,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(RegistrationStepRequest $request): RedirectResponse
    {
        $payload = $this->payload($request);

        RegistrationStep::create([
            ...$payload,
            'key' => 'custom_'.Str::lower(Str::random(8)),
            'is_core' => false,
            'position' => (RegistrationStep::where('section', $payload['section'])->max('position') ?? 0) + 1,
        ]);

        return redirect()->route('admin.steps.index')->with('status', 'Step added.');
    }

    public function edit(RegistrationStep $registrationStep): View
    {
        return view('admin.steps.form', ['step' => $registrationStep]);
    }

    public function update(RegistrationStepRequest $request, RegistrationStep $registrationStep): RedirectResponse
    {
        $payload = $this->payload($request, $registrationStep);

        if (! $payload['is_active'] && $this->isLastActive($registrationStep)) {
            return back()->withInput()->with('error', 'At least one step must stay active.');
        }

        $registrationStep->update($payload);

        return redirect()->route('admin.steps.index')->with('status', 'Step updated.');
    }

    public function toggle(RegistrationStep $registrationStep): RedirectResponse
    {
        if ($registrationStep->is_active && $this->isLastActive($registrationStep)) {
            return back()->with('error', 'At least one step must stay active.');
        }

        $registrationStep->update(['is_active' => ! $registrationStep->is_active]);

        return back()->with('status', $registrationStep->is_active ? 'Step enabled.' : 'Step disabled.');
    }

    public function move(Request $request, RegistrationStep $registrationStep): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $steps = RegistrationStep::ordered()->where('section', $registrationStep->section)->get()->values();
        $index = $steps->search(fn (RegistrationStep $step) => $step->is($registrationStep));
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($steps[$swapWith])) {
            $reordered = $steps->all();
            [$reordered[$index], $reordered[$swapWith]] = [$reordered[$swapWith], $reordered[$index]];

            foreach ($reordered as $position => $step) {
                $step->update(['position' => $position + 1]);
            }
        }

        return back();
    }

    public function destroy(RegistrationStep $registrationStep): RedirectResponse
    {
        if ($registrationStep->is_core) {
            return back()->with('error', 'Built-in steps cannot be deleted. Disable them instead.');
        }

        if ($registrationStep->is_active && $this->isLastActive($registrationStep)) {
            return back()->with('error', 'At least one step must stay active.');
        }

        $registrationStep->delete();

        return back()->with('status', 'Step deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RegistrationStepRequest $request, ?RegistrationStep $existing = null): array
    {
        $data = $request->validated();
        $type = $existing?->is_core ? $existing->type : StepType::from($data['type']);

        return [
            'type' => $type,
            'section' => $existing?->is_core ? $existing->section : StepSection::from($data['section']),
            'label' => $data['label'],
            'question' => $data['question'],
            'options' => $type === StepType::Choice ? $request->optionMap() : null,
            'is_required' => $data['is_required'],
            'is_active' => $data['is_active'],
        ];
    }

    private function isLastActive(RegistrationStep $step): bool
    {
        return RegistrationStep::active()->whereKeyNot($step->getKey())->doesntExist();
    }
}
