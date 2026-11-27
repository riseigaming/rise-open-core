<?php

namespace App\Http\Controllers\Cms\Front;

use App\Http\Controllers\Controller;
use App\Models\CmsUserTransaction;
use App\Services\Payments\NovaDepositService;
use App\Services\Payments\NovaGatewayClient;
use App\Services\Restrictions\CentralRestrictionPolicyService;
use App\Services\Restrictions\ProductCountryRestrictionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class NovaDepositController extends Controller
{
    public function __construct(
        private readonly NovaDepositService $deposits,
        private readonly ProductCountryRestrictionGuard $countries,
    ) {
    }

    public function index(Request $request, NovaGatewayClient $gateway)
    {
        if ($blocked = $this->countryBlock($request)) {
            return $blocked;
        }
        $customer = auth('cms')->user();
        $rail = $this->deposits->availableRail($customer);
        $transactions = $this->deposits->deposits()->where('cms_user_id', $customer->id)->latest('id')->paginate(20);

        return view('cms.global.nova-deposits', [
            'rail' => $rail, 'transactions' => $transactions, 'requestKey' => (string) Str::uuid(),
            'currency' => $customer->currency, 'remote' => $gateway->remote(), 'creditAllowed' => $gateway->creditAllowed(),
        ]);
    }

    public function store(Request $request)
    {
        if ($blocked = $this->countryBlock($request)) {
            return $blocked;
        }
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'string', 'regex:/\A\d{1,12}(?:\.\d{1,2})?\z/', 'numeric', 'min:1'],
        ]);
        $deposit = $this->deposits->start(auth('cms')->user(), $data['request_key'], $data['amount']);

        return $this->respond($request, $deposit);
    }

    public function recheck(Request $request, string $reference)
    {
        if ($blocked = $this->countryBlock($request)) {
            return $blocked;
        }
        $deposit = $this->deposits->deposits()->where('transactionId', $reference)
            ->where('cms_user_id', auth('cms')->id())->firstOrFail();

        return $this->respond($request, $this->deposits->recheck($deposit));
    }

    private function countryBlock(Request $request)
    {
        $decision = $this->countries->decide($request, CentralRestrictionPolicyService::PRODUCT_PAYMENTS);
        return !$decision->allowed ? $this->countries->blockedResponse($request, $decision) : null;
    }

    private function respond(Request $request, CmsUserTransaction $deposit)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'reference' => $deposit->transactionId, 'status' => $deposit->status,
                'asset' => $deposit->transaction_asset, 'amount' => $deposit->exact_amount,
                'payment' => $deposit->execution_context_snapshot,
            ])->header('Cache-Control', 'private, no-store');
        }
        return redirect()->route('cms.wallet.nova.index');
    }
}
