@extends('cms.admin.index')
@section('title','Nova deposit history')
@section('content')
<div class="container-fluid py-3">
    <h1 class="h2">Nova deposit history</h1>
    @unless(app(\App\Services\Payments\NovaGatewayClient::class)->remote())
        <a href="{{ route('cms.admin.transactions.manualOverride.create') }}">Canonical finance</a>
    @endunless
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Reference</th><th>Customer ID</th><th>Gateway status</th><th>Provider reference</th><th>Wallet ID</th><th>Canonical credit</th></tr></thead>
            <tbody>
            @foreach($transactions as $deposit)
                <tr>
                    <td>{{ $deposit->transactionId }}</td><td>{{ $deposit->cms_user_id }}</td>
                    <td>{{ $deposit->execution_context_snapshot['status'] ?? 'UNCONFIRMED' }}</td>
                    <td>{{ $deposit->execution_context_snapshot['provider_reference'] ?? '—' }}</td>
                    <td>{{ $deposit->settled_wallet_account_id ?? $deposit->source_wallet_account_id }}</td>
                    <td>{{ $deposit->status === \App\Enum\TransactionStatusEnum::Success ? $deposit->exact_amount . ' ' . $deposit->transaction_asset : 'Pending' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $transactions->links() }}
</div>
@endsection
