<?php
namespace App\Services\ExternalGames;
use App\Models\{CmsSensitiveMutationEvent,ExternalGameSession,CmsUserTransaction,CmsUser};
use Illuminate\Http\Request;

/** Adapter to Rise's existing audit storage. Ops Radar itself is outside this transfer. */
final class ExternalGameAudit
{
    public function __call(string $event,array $args): void
    {
        $request=null;$subject=null;$meta=[];
        foreach ($args as $arg) {
            if ($arg instanceof Request) $request=$arg;
            elseif ($arg instanceof ExternalGameSession) { $subject=$arg;$meta['session_id']=$arg->id; }
            elseif ($arg instanceof CmsUserTransaction) $meta['transaction_id']=$arg->transactionId;
            elseif ($arg instanceof CmsUser) $subject??=$arg;
            elseif (is_array($arg)) {
                if (is_array($arg['metadata']??null)) $arg=array_merge($arg,$arg['metadata']);
                foreach (['reason','game_id','provider_alias','provider_game_id','currency','demo','request_id','financial_identity_count','candidate_count','chosen_session_id','wallet_account_id','wallet_currency','confidence','source_table','source_id','subject_user_id','severity','wallet_direction','limit','limit_currency'] as $k)
                    if (isset($arg[$k]) && is_scalar($arg[$k])) $meta[$k]=$arg[$k];
            }
        }
        CmsSensitiveMutationEvent::create([
            'event_type'=>'external_games.'.substr($event,0,140),'subject_type'=>$subject?->getMorphClass(),
            'subject_id'=>$subject?->getKey(),'actor_type'=>CmsUser::class,'actor_id'=>auth('cms')->id(),
            'source'=>'external_games','route_name'=>$request?->route()?->getName(),
            'request_path'=>$request?->path(),'metadata'=>$meta,
        ]);
    }
    public function emit(string $event,?Request $request,array $meta): void { $this->__call($event,[$request,$meta]); }
}
