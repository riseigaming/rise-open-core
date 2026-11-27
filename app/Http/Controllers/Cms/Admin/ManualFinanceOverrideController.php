<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\FinanceManualOverride;
use App\Models\WalletAccount;
use App\Services\Finance\ManualFinanceOverrideService;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManualFinanceOverrideController extends Controller
{
    public function __construct(
        private readonly ManualFinanceOverrideService $overrides,
        private readonly AssetWalletService $assetWallets,
        private readonly SpendableWalletService $spendableWallets,
    ) {

    }

    public function create(Request $request)
    {
        abort_unless(Schema::hasTable('finance_manual_overrides'), 503, 'Manual finance override storage is unavailable.');

        $transaction = $this->resolveTransaction((string) $request->query('transaction_id', ''));
        $customerId = $transaction?->cms_user_id ?: (int) $request->query('customer_id', 0);
        $customer = $customerId > 0
            ? CmsUser::query()->with('parentAccount')->whereKey($customerId)->where('role', 'customer')->first()
            : null;
        $wallets = collect();

        if ($customer instanceof CmsUser && Schema::hasTable('wallet_accounts')) {
            $owner = $this->assetWallets->resolveOwnerUser($customer);
            $wallets = WalletAccount::query()
                ->where('cms_user_id', $owner->id)
                ->where('status', 'active')
                ->orderBy('asset')
                ->orderBy('wallet_type')
                ->get()
                ->map(function (WalletAccount $wallet) use ($customer): WalletAccount {
                    $context = $this->spendableWallets->resolveForWallet($customer, $wallet);
                    $wallet->setAttribute('authoritative_balance', $context instanceof SpendableWalletContext
                        ? $this->spendableWallets->currentBalance($context)
                        : (float) $wallet->available_balance);

                    return $wallet;
                });
        }

        $history = FinanceManualOverride::query()
            ->with(['actor:id,username,first_name,last_name', 'customer:id,username,first_name,last_name'])
            ->when($transaction, fn ($query) => $query->where('cms_user_transaction_id', $transaction->id))
            ->when(!$transaction && $customer, fn ($query) => $query->where('cms_user_id', $customer->id))
            ->latest('id')
            ->limit(25)
            ->get();

        return view('cms.admin.transactions.manual-override', [
            'pageTitle' => __('admin.transactions.manual_override_title'),
            'transaction' => $transaction,
            'customer' => $customer,
            'wallets' => $wallets,
            'history' => $history,
            'rounds' => $customer ? \App\Models\InternalGameRound::where('cms_user_id',$this->assetWallets->resolveOwnerUser($customer)->id)->where('is_demo',false)->latest('id')->limit(20)->get() : collect(),
            'operationId' => (string) Str::uuid(),
            'economicActions' => ManualFinanceOverrideService::ECONOMIC_ACTIONS,
            'externalResolutions' => ManualFinanceOverrideService::EXTERNAL_RESOLUTIONS,
            'operationalStatuses' => ManualFinanceOverrideService::OPERATIONAL_STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'operation_id' => ['required', 'string', 'max:120'],
            'customer_id' => ['required', 'integer', 'min:1'],
            'transaction_id' => ['prohibited'],
            'new_status' => ['prohibited'],
            'external_resolution' => ['required', 'string'],
            'economic_action' => ['required', 'in:manual_credit,manual_debit'],
            'wallet_account_id' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:16'],
            // Scale is wallet-specific and is checked by the service after the
            // canonical wallet context is resolved.
            'amount' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (!is_string($value) && !is_int($value)) {
                    $fail('Amount must be a decimal string.');
                }
            }],
            'reason' => ['required', 'string', 'max:500'],
            'evidence_reference' => ['nullable', 'string', 'max:500'],
            'settlement_evidence' => ['prohibited'],
            'confirm_override' => ['accepted'],
        ]);

        try {
            $result = $this->overrides->execute($validated, auth('cms')->user());
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['manual_override' => $exception->getMessage()]);
        } catch (\App\Services\CryptoControl\SettlementDeferred $exception) {
            return $request->expectsJson()
                ? response()->json(['status' => 503, 'code' => 'CRYPTO_SETTLEMENT_DEFERRED'], 503)
                : back()->withErrors(['manual_override' => __('admin.copy.generic_error')])->withInput();
        } catch (Throwable $exception) {
            Log::warning('Manual finance override failed.', [
                'actor_admin_id' => auth('cms')->id(),
                'customer_id' => (int) ($validated['customer_id'] ?? 0),
                'transaction_id' => (int) ($validated['transaction_id'] ?? 0),
                'exception_class' => get_class($exception),
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => __('admin.copy.generic_error')], 500)
                : back()->withErrors(['manual_override' => __('admin.copy.generic_error')])->withInput();
        }

        $message = $result['idempotent_replay']
            ? __('admin.transactions.manual_override_replayed')
            : __('admin.transactions.manual_override_applied');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 200,
                'message' => $message,
                'manual_override_id' => (int) $result['audit']->id,
                'idempotent_replay' => (bool) $result['idempotent_replay'],
            ]);
        }

        return redirect()->route('cms.admin.transactions.manualOverride.create', [
            'transaction_id' => $validated['transaction_id'] ?? null,
            'customer_id' => $validated['customer_id'],
        ])->with('manual_override_status', $message);
    }

    private function resolveTransaction(string $reference): ?CmsUserTransaction
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        return CmsUserTransaction::query()
            ->with('user.parentAccount')
            ->where(function ($query) use ($reference): void {
                $query->where('transactionId', $reference);
                if (ctype_digit($reference)) {
                    $query->orWhere('id', (int) $reference);
                }
            })
            ->first();
    }
}
