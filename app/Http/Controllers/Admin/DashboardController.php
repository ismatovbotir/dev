<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ShopRequest;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const CHART_DAYS = 14;

    public function __invoke(): View
    {
        $thisWeek = ShopRequest::where('created_at', '>=', now()->subDays(7))->count();
        $previousWeek = ShopRequest::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();

        return view('admin.dashboard', [
            'total' => ShopRequest::count(),
            'awaitingCount' => ShopRequest::whereIn('status', [RequestStatus::New, RequestStatus::InProgress])->count(),
            'thisWeek' => $thisWeek,
            'weekChange' => $previousWeek > 0 ? (int) round(($thisWeek - $previousWeek) / $previousWeek * 100) : null,
            'averageResponse' => $this->averageResponse(),
            'series' => $this->dailySeries(),
            'awaiting' => ShopRequest::whereIn('status', [RequestStatus::New, RequestStatus::InProgress])->oldest()->limit(6)->get(),
        ]);
    }

    /**
     * @return list<array{date: Carbon, count: int}>
     */
    private function dailySeries(): array
    {
        $counts = ShopRequest::where('created_at', '>=', today()->subDays(self::CHART_DAYS - 1))
            ->get(['created_at'])
            ->countBy(fn (ShopRequest $request) => $request->created_at->toDateString());

        return collect(range(self::CHART_DAYS - 1, 0))
            ->map(fn (int $daysAgo) => today()->subDays($daysAgo))
            ->map(fn (Carbon $date) => ['date' => $date, 'count' => $counts[$date->toDateString()] ?? 0])
            ->all();
    }

    private function averageResponse(): ?string
    {
        $minutes = ShopRequest::whereNotNull('answered_at')
            ->latest('answered_at')
            ->limit(100)
            ->get(['created_at', 'answered_at'])
            ->map(fn (ShopRequest $request) => $request->created_at->diffInMinutes($request->answered_at));

        if ($minutes->isEmpty()) {
            return null;
        }

        $average = (int) round($minutes->avg());

        return $average >= 60 ? intdiv($average, 60).' h '.($average % 60).' min' : $average.' min';
    }
}
