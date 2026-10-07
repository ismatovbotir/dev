<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        return view('admin.clients.index', [
            'clients' => TelegramUser::query()
                ->withCount('shopRequests')
                ->with('latestShopRequest')
                ->latest()
                ->paginate(20),
        ]);
    }
}
