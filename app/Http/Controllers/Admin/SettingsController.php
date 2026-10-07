<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdatePlanExamplesRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.settings.edit', [
            'user' => $request->user(),
            'bot' => $this->botSummary(),
            'planExamples' => Setting::planExamples(),
        ]);
    }

    public function updatePlanExamples(UpdatePlanExamplesRequest $request): RedirectResponse
    {
        $examples = Setting::planExamples();

        foreach (array_map('intval', (array) $request->input('remove')) as $id) {
            if (isset($examples[$id])) {
                Storage::disk('local')->delete($examples[$id]['path']);
                unset($examples[$id]);
            }
        }

        foreach ((array) $request->file('examples') as $file) {
            $examples[max([0, ...array_keys($examples)]) + 1] = [
                'path' => $file->store('plan-examples', 'local'),
                'name' => $file->getClientOriginalName(),
            ];
        }

        Setting::write(Setting::PLAN_EXAMPLES, $examples);

        return back()->with('status', 'Floor plan examples saved.');
    }

    public function planExample(int $example): StreamedResponse
    {
        $path = Setting::planExamples()[$example]['path'] ?? null;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return back()->with('status', 'Password updated.');
    }

    /**
     * @return list<array{label: string, value: string, ok?: bool}>
     */
    private function botSummary(): array
    {
        $adminChat = config('services.telegram.admin_chat_id');

        return [
            ['label' => 'Bot token', 'value' => filled(config('services.telegram.token')) ? 'Configured' : 'Not set', 'ok' => filled(config('services.telegram.token'))],
            ['label' => 'Webhook secret', 'value' => filled(config('services.telegram.webhook_secret')) ? 'Configured' : 'Not set', 'ok' => filled(config('services.telegram.webhook_secret'))],
            ['label' => 'Webhook URL', 'value' => route('telegram.webhook')],
            ['label' => 'New-request alerts chat', 'value' => filled($adminChat) ? (string) $adminChat : 'Not set', 'ok' => filled($adminChat)],
        ];
    }
}
