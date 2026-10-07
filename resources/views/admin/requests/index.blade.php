@extends('layouts.admin')

@section('title', 'Requests')

@section('content')
    <x-page-header title="Requests" subtitle="Leads collected by the Telegram bot." />

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 px-3 pt-3">
            @php $total = $counts->sum(); @endphp
            <a href="{{ route('admin.requests.index', array_filter(['q' => $search])) }}"
               @class(['-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium', 'border-brand-600 text-brand-700' => ! $status, 'border-transparent text-slate-500 hover:text-slate-900' => $status])>
                All <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $total }}</span>
            </a>
            @foreach (\App\Enums\RequestStatus::cases() as $case)
                <a href="{{ route('admin.requests.index', array_filter(['status' => $case->value, 'q' => $search])) }}"
                   @class(['-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium', 'border-brand-600 text-brand-700' => $status === $case, 'border-transparent text-slate-500 hover:text-slate-900' => $status !== $case])>
                    {{ $case->label() }} <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $counts[$case->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-4">
            @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
            <div class="relative min-w-60 flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name, phone, brand or location"
                       class="w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm placeholder:text-slate-400 focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
            </div>
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.requests.index', array_filter(['status' => $status?->value])) }}" class="px-2 py-2 text-sm text-slate-500 hover:text-slate-900">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Brand</th>
                        <th class="px-4 py-3 font-medium">Location</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Received</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($requests as $item)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.requests.show', $item) }}" class="flex items-center gap-3">
                                    <x-avatar :name="$item->name ?? '?'" />
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $item->name ?? 'Request #'.$item->id }}</div>
                                        <div class="text-xs text-slate-500">#{{ $item->id }} · {{ $item->phone ?? '—' }}</div>
                                    </div>
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $item->brand ?? '—' }}</td>
                            <td class="max-w-56 truncate px-4 py-3">{{ $item->location_text ?? '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$item->status" /></td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500" title="{{ $item->created_at->format('d.m.Y H:i') }}">{{ $item->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-14 text-center">
                                <x-icon name="inbox" class="mx-auto size-8 text-slate-300" />
                                <p class="mt-2 text-sm text-slate-500">No requests found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
@endsection
