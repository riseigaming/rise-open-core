@extends('cms.global.index')
@section('pageContent')
<section class="globalSurfacePage"><div class="container py-4">
    <h1>Nova deposits</h1>
    @unless($remote)<a href="{{ route('cms.wallet.index') }}">Canonical wallet</a>@endunless
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @if($rail)
        @if(!$remote)
            <p>Local test network. Use synthetic funds only.</p>
        @elseif(!$creditAllowed)
            <p role="alert">Automatic wallet credit is disabled. Do not send funds.</p>
        @endif
        <form method="post" action="{{ route('cms.wallet.nova.store') }}">
            @csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
            <label for="nova-amount">Reference amount ({{ $currency }})</label>
            <input id="nova-amount" name="amount" type="number" min="1" step=".01" value="{{ old('amount') }}" required>
            <button type="submit">Request {{ $rail->asset }} / {{ $rail->network }} payment</button>
        </form>
    @else
        <p>Nova deposits are unavailable.</p>
    @endif
    @foreach($transactions as $deposit)
        <article class="my-3">
            <h2>{{ $deposit->status === \App\Enum\TransactionStatusEnum::Success ? 'Credited' : 'Pending' }}</h2>
            <p style="overflow-wrap:anywhere">{{ $deposit->transactionId }}</p>
            @if($details = $deposit->execution_context_snapshot)
                <p>{{ $details['status'] }} · {{ $details['requested_amount_crypto'] }} {{ $deposit->transaction_asset }}
                    ({{ $deposit->crypto_deposit_context['network_mode'] }})</p>
                @if($deposit->status === \App\Enum\TransactionStatusEnum::Pending
                    && $details['status'] === 'AWAITING_PAYMENT' && !$details['quote_expired']
                    && strtotime($details['expires_at']) > time())
                    <p style="overflow-wrap:anywhere">Address <code>{{ $details['pay_address'] }}</code></p>
                @endif
            @endif
            @if($rail && $deposit->status === \App\Enum\TransactionStatusEnum::Pending)
                @if(!$deposit->provider_transaction_id)
                    <form method="post" action="{{ route('cms.wallet.nova.store') }}">
                        @csrf
                        <input type="hidden" name="request_key" value="{{ $deposit->crypto_deposit_context['request_key'] }}">
                        <input type="hidden" name="amount" value="{{ $deposit->crypto_deposit_context['fiat_amount'] }}">
                        <button type="submit">Retry original request</button>
                    </form>
                @endif
                <form method="post" action="{{ route('cms.wallet.nova.recheck', $deposit->transactionId) }}">
                    @csrf
                    <button type="submit">Check payment</button>
                </form>
            @endif
        </article>
    @endforeach
    {{ $transactions->links() }}
</div></section>
@endsection
