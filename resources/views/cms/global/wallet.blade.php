@extends('cms.global.index')
@push('head')<style>.canonicalWallet{min-width:0;max-width:100%}.canonicalWallet #promo-redeem{display:flex;flex-wrap:wrap;gap:8px}.canonicalWallet #promo-redeem label{width:100%}.canonicalWallet article{margin:1rem 0;padding:1rem;border:1px solid #697681;border-radius:12px}.canonicalWalletScroll{max-width:100%;overflow:auto}.canonicalWallet table{width:100%;border-collapse:collapse;min-width:640px}.canonicalWallet td,.canonicalWallet th{padding:8px;text-align:left;border-bottom:1px solid #697681}.canonicalWallet input,.canonicalWallet select{max-width:100%;padding:8px}.canonicalWallet form{margin:12px 0}.canonicalWallet .globalPrimaryAction{margin:4px}</style>@endpush
@section('pageContent')
<section class="globalSurfacePage"><div class="container py-4 canonicalWallet"><h1>Canonical wallet</h1><p>Local synthetic funds · no external payments</p><a href="{{ route('cms.games.index') }}">Global catalog</a>
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@if(session('wallet_status'))<p role="status">{{ session('wallet_status') }}</p>@endif
@foreach($wallets as $w)<article><h2 data-wallet-id="{{ $w->id }}">{{ $w->asset }} · {{ $w->available_balance }}</h2>@if($activeWalletId===$w->id)<p>Active wallet</p>@endif<form method="post" action="{{ route('cms.wallet.switch') }}">@csrf<input type="hidden" name="wallet_account_id" value="{{ $w->id }}"><button class="globalPrimaryAction">Select {{ $w->asset }}</button></form></article>@endforeach
<form method="post" action="{{ route('cms.wallet.provision') }}">@csrf<select name="currency" aria-label="Wallet currency">@foreach(['TRY','USD','EUR'] as $c)<option>{{ $c }}</option>@endforeach</select><button class="globalPrimaryAction">Open canonical wallet</button></form>
@if(config('nova_payments.enabled'))<p><a href="{{ route('cms.wallet.nova.index') }}">Nova deposits</a></p>@endif
<h2>Promo code</h2><form method="post" action="{{ route('cms.promotionCode.redeem') }}" id="promo-redeem">@csrf<label for="promoCode">Promo code</label><input id="promoCode" name="promoCode" required><button class="globalPrimaryAction">Redeem code</button></form>
<p id="promo-result" role="status" aria-live="polite"></p><h2>Transactions</h2><div class="canonicalWalletScroll" tabindex="0"><table><thead><tr><th>Reference</th><th>Action</th><th>Amount</th><th>After</th></tr></thead><tbody>@foreach($transactions as $t)<tr data-transaction-id="{{ $t->id }}"><td>{{ $t->transactionId }}</td><td>{{ $t->event_type }}</td><td>{{ $t->exact_amount }} {{ $t->currency }}</td><td>{{ $t->exact_after_balance }}</td></tr>@endforeach</tbody></table></div>
<h2>Ledger</h2><div class="canonicalWalletScroll" tabindex="0"><table><tbody>@foreach($ledger as $e)<tr data-ledger-id="{{ $e->id }}"><td>{{ $e->entry_type }}</td><td>{{ $e->direction }}</td><td>{{ $e->amount }} {{ $e->asset }}</td><td>{{ $e->balance_before }} → {{ $e->balance_after }}</td></tr>@endforeach</tbody></table></div>
</div></section>
<script>
document.getElementById('promo-redeem').addEventListener('submit', async function(event) {
 event.preventDefault();const button=this.querySelector('button'),result=document.getElementById('promo-result');button.disabled=true;
 try {const response=await fetch(this.action,{method:'POST',body:new FormData(this),headers:{Accept:'application/json'},credentials:'same-origin'});const data=await response.json();result.textContent=data.message || String(data.status);if(response.ok && data.status===200){this.reset();setTimeout(()=>location.reload(),800);}}
 catch(error){result.textContent='Request could not be confirmed. Reload to check the ledger before retrying.';}finally{button.disabled=false;}
});
</script>
@endsection
