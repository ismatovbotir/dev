@extends('layouts.admin')

@section('title', 'Clients')

@section('content')
    <x-page-header title="Clients" subtitle="People who have talked to the Telegram bot." />

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 font-medium">Telegram</th>
                        <th class="px-4 py-3 font-medium">Language</th>
                        <th class="px-4 py-3 font-medium">Requests</th>
                        <th class="px-4 py-3 font-medium">Last request</th>
                        <th class="px-4 py-3 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($clients as $client)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $client->first_name ?? '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $client->username ? '@'.$client->username : 'ID '.$client->chat_id }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $client->language ? strtoupper($client->language->value) : '—' }}</td>
                            <td class="px-4 py-3">{{ $client->shop_requests_count }}</td>
                            <td class="px-4 py-3">
                                @if ($client->latestShopRequest)
                                    <a class="font-medium text-slate-900 hover:underline" href="{{ route('admin.requests.show', $client->latestShopRequest) }}">#{{ $client->latestShopRequest->id }}</a>
                                    <span class="ml-1 text-xs text-slate-500">{{ $client->latestShopRequest->brand }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $client->created_at->format('d.m.Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-14 text-center">
                                <x-icon name="users" class="mx-auto size-8 text-slate-300" />
                                <p class="mt-2 text-sm text-slate-500">No clients yet.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $clients->links() }}</div>
@endsection
