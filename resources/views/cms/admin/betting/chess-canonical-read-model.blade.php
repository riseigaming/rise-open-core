<section class="card my-3" data-chess-canonical-read-model><div class="card-body">
<h2>ChessBet · canonical finance</h2>
<p>Market controls and financial settlement are separate. Settlement requires an authorized signed partner handoff and uses the canonical wallet and ledger.</p>
<p data-chess-pending-count>Pending recurring reservations: {{ count($pendingChessReservations ?? []) }}</p>
<div style="overflow-x:auto"><table class="table"><thead><tr><th>Reservation</th><th>Account / wallet</th><th>Stake / currency</th><th>Payout</th><th>Status</th><th>Transactions</th></tr></thead><tbody>
@foreach(($chessRecentReservations ?? []) as $reservation)
<tr data-chess-reservation="{{ $reservation->cms_reservation_id }}"><td>{{ $reservation->cms_reservation_id }}<br>{{ $reservation->placed_bet_id }}</td><td>{{ $reservation->user?->username }} / {{ data_get($reservation->request_payload,'wallet_account_id') }}</td><td>{{ $reservation->stake_amount }} {{ $reservation->currency }}</td><td>{{ $reservation->payout_amount }}</td><td>{{ $reservation->status }}</td><td>{{ $reservation->reserve_transaction_id }} / {{ $reservation->release_transaction_id }} / {{ $reservation->settlement_transaction_id }}</td></tr>
@endforeach
</tbody></table></div></div></section>
