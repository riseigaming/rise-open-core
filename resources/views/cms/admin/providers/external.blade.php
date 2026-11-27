@extends('cms.admin.index')
@section('title','External Games')
@section('content')
<div class="container-fluid py-3"><a href="{{ route('cms.admin.providers.index') }}">Providers</a><h1>External Games</h1>
@include('cms.admin.catalog.notice')
<form method="get" action="{{ route('cms.admin.externalGames.edit') }}">
    <label>Connector <select name="provider_alias">
        @foreach($providerAliases as $alias)
            <option value="{{ $alias }}" @selected($alias === $selectedAlias)>{{ $alias }}</option>
        @endforeach
    </select></label>
    <button type="submit">View connector</button>
</form>
<p data-provider-configuration="{{ $selectedAlias }}">Connector {{ $selectedAlias }}: {{ $configured?'configured':'not configured' }}. Credentials remain in server configuration.</p>
<form method="post" action="{{ route('cms.admin.externalGames.sync') }}">@csrf<input type="hidden" name="provider_alias" value="{{ $selectedAlias }}"><button data-import-catalog class="btn btn-primary">Import provider catalog</button></form>
<form method="post" action="{{ route('cms.admin.externalGames.update') }}" class="card card-body my-3">@csrf
@foreach(['enabled'=>'Enabled','demo_enabled'=>'Demo enabled','emergency_stop'=>'Emergency stop'] as $key=>$label)<label>{{ $label }}<select name="{{ $key }}"><option value="0" @selected(!$controls[$key])>No</option><option value="1" @selected($controls[$key])>Yes</option></select></label>@endforeach
<label>Pilot eligibility <select name="pilot_policy">@foreach(['default_open','manual_approval_for_new_users','pilot_cohort_only'] as $policy)<option @selected($controls['pilot_policy']===$policy)>{{ $policy }}</option>@endforeach</select></label>
<input name="reason" required minlength="5" placeholder="Change reason"><button data-save-external-controls>Save controls</button></form>
@if($provider)<a href="{{ route('cms.admin.providers.edit',$provider) }}">Provider visibility / maintenance / identity</a>@endif
<h2>Imported games / rollout</h2><div class="table-responsive"><table class="table"><thead><tr><th>Source game</th><th>Visibility</th><th>Rollout</th></tr></thead><tbody>
@foreach($games as $game)<tr data-external-game="{{ $game->id }}"><td>{{ $game->name }} · {{ $game->reference_id }}<br><code>{{ $rollout->decisionForGame($game)['provider_key'] }}</code><br><a href="{{ route('cms.admin.games.edit',$game) }}">Catalog / curation / maintenance</a></td><td>{{ $game->is_restricted?'Restricted':'Visible' }}</td><td>
<form method="post" action="{{ route('cms.admin.externalGames.rollout') }}">@csrf<input type="hidden" name="game_id" value="{{ $game->id }}"><select name="state">@foreach(['hidden','preview','canary','active','maintenance','disabled'] as $state)<option @selected($rollout->decisionForGame($game)['state']===$state)>{{ $state }}</option>@endforeach</select><input name="reason" required minlength="5" placeholder="Change reason"><button>Save rollout</button></form></td></tr>@endforeach
</tbody></table></div><h2>Provider rollout</h2><form method="post" action="{{ route('cms.admin.externalGames.rollout') }}">@csrf<input name="provider_key" required placeholder="Source vendor key (see game rows)"><select name="state">@foreach(['hidden','preview','canary','active','maintenance','disabled'] as $state)<option>{{ $state }}</option>@endforeach</select><input name="reason" required minlength="5" placeholder="Change reason"><button>Save provider rollout</button></form>
<h2>Risk / eligibility</h2><form method="get" action="{{ route('cms.admin.externalGames.edit') }}"><label>User ID <input name="user_id" type="number" min="1" value="{{ $selectedUser?->id }}"></label><button>Inspect eligibility</button></form>@if($eligibility)<pre data-external-eligibility>{{ json_encode($eligibility,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>@endif<pre data-external-risk>{{ json_encode($risk['limits'],JSON_PRETTY_PRINT) }}</pre>
<form method="post" action="{{ route('cms.admin.externalGames.risk') }}">@csrf
<label>Decision <select name="kind">@foreach(['global_risk','user_risk','user_risk_revoke','eligibility_grant','eligibility_revoke'] as $kind)<option>{{ $kind }}</option>@endforeach</select></label>
<label>User ID (user decision) <input name="cms_user_id" type="number" min="1"></label>
@foreach(['max_single_bet','rolling_24h_stake_limit','rolling_24h_net_loss_limit'] as $k)<label>{{ $k }} (TRY equivalent)<input name="{{ $k }}" type="number" min="0" step="0.01" value="{{ $risk['limits'][$k] }}"></label>@endforeach
<label><input type="checkbox" name="confirm_material_change" value="1"> Confirm material global limit change</label><label>Expiry <input name="expires_at" type="datetime-local"></label><input name="reason" required minlength="5" placeholder="Change reason"><button>Save audited decision</button></form>
<h2>Sessions</h2><div class="table-responsive"><table class="table"><tr><th>ID</th><th>Game / user</th><th>Wallet / currency</th><th>Status / callback</th></tr>@foreach($sessions as $s)<tr data-external-session="{{ $s->id }}"><td>{{ $s->id }}</td><td>{{ $s->provider_game_id }} / {{ $s->cms_user_id }}</td><td>{{ $s->wallet_account_id }} / {{ $s->wallet_currency }}</td><td>{{ $s->status }} / {{ $s->last_callback_status }}</td></tr>@endforeach</table></div>
<h2>Connector summary</h2><pre data-external-provider-summary>{{ json_encode($providerSummary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
<h2>Callback health</h2><pre data-external-health>{{ json_encode($health,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
<h2>Finance reconciliation</h2><pre data-external-reconciliation>{{ json_encode($reconciliation,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
</div>
@endsection
