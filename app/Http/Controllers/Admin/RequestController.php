<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplyToRequestRequest;
use App\Models\ShopRequest;
use App\Services\Telegram\RequestReplySender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = RequestStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q'));

        $requests = ShopRequest::query()
            ->with('telegramUser')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('location_text', 'like', $like);
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = ShopRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.requests.index', [
            'requests' => $requests,
            'counts' => $counts,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(ShopRequest $shopRequest): View
    {
        return view('admin.requests.show', [
            'shopRequest' => $shopRequest->load(['telegramUser', 'answeredBy']),
        ]);
    }

    public function reply(ReplyToRequestRequest $request, ShopRequest $shopRequest, RequestReplySender $sender): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('drawing')) {
            if ($shopRequest->drawing_path !== null) {
                Storage::delete($shopRequest->drawing_path);
            }

            $file = $request->file('drawing');
            $shopRequest->drawing_path = $file->store('drawings');
            $shopRequest->drawing_name = $file->getClientOriginalName();
        }

        $shopRequest->fill([
            'admin_comment' => $data['admin_comment'] ?? null,
            'answered_by' => $request->user()->id,
        ])->save();

        try {
            $sender->send($shopRequest);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Saved, but sending to Telegram failed: '.$exception->getMessage());
        }

        $shopRequest->update([
            'status' => RequestStatus::Answered,
            'answered_at' => now(),
            'delivered_at' => now(),
        ]);

        return back()->with('status', 'Reply sent to the client in Telegram.');
    }

    public function updateStatus(Request $request, ShopRequest $shopRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(RequestStatus::class)],
        ]);

        $shopRequest->update(['status' => $data['status']]);

        return back()->with('status', 'Status updated.');
    }

    public function drawing(ShopRequest $shopRequest): StreamedResponse
    {
        abort_if($shopRequest->drawing_path === null || ! Storage::exists($shopRequest->drawing_path), 404);

        return Storage::response($shopRequest->drawing_path, $shopRequest->drawing_name);
    }

    public function attachment(ShopRequest $shopRequest, int $index): StreamedResponse
    {
        $path = $shopRequest->answers[$index]['file'] ?? null;

        abort_if($path === null || ! Storage::exists($path), 404);

        return Storage::response($path);
    }
}
