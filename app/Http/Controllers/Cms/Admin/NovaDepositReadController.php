<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payments\NovaDepositService;
use Illuminate\Http\Request;

final class NovaDepositReadController extends Controller
{
    public function __invoke(Request $request, NovaDepositService $deposits)
    {
        $filters = $request->validate(['transaction_id' => ['nullable', 'integer', 'min:1']]);

        return view('cms.admin.transactions.nova-deposits', [
            'transactions' => $deposits->deposits()->when(isset($filters['transaction_id']), fn ($q) => $q->whereKey($filters['transaction_id']))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }
}
