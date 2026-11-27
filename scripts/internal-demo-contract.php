<?php
// Explicit additive demo contract. Historical B1 independent results are unchanged.
$web = ['web','cmsUserActivity','cmsSessionCheck','EnforceSiteCountryRestriction','PublicFrontendMaintenance','cmsLanguage'];
return [
    'migration_names'=>['2026_10_28_120100_create_internal_game_demo_tables'],
    'required_columns'=>[
        'internal_game_sessions'=>['id','public_id','session_token','provider_id','cms_game_id','cms_user_id','root_user_id','game_code','currency','status','is_demo','launch_payload','launched_at','last_activity_at','expires_at','ended_at','created_at','updated_at'],
        'internal_game_rounds'=>['id','internal_game_session_id','provider_id','cms_game_id','cms_user_id','root_user_id','round_id','game_code','session_public_id','status','currency','is_demo','bet_amount','win_amount','refund_amount','request_ref','correlation_ref','raw_result','result_summary','started_at','settled_at','created_at','updated_at'],
        'internal_game_round_events'=>['id','internal_game_round_id','internal_game_session_id','event_type','event_status','request_ref','correlation_ref','message','payload','created_at'],
    ],
    'routes'=>[
        ['method'=>'GET','uri'=>'/cms/internal-games/play/{sessionToken}','name'=>'cms.internalGames.play','controller'=>'App\\Http\\Controllers\\Cms\\Front\\DiceController@play','middleware'=>array_merge($web,['throttle:internal-games-play']),'id'=>'INTERNAL-DEMO-HTTP-001'],
        ['method'=>'POST','uri'=>'/cms/internal-games/dice/roll','name'=>'cms.internalGames.dice.roll','controller'=>'App\\Http\\Controllers\\Cms\\Front\\DiceController@roll','middleware'=>array_merge($web,['throttle:internal-games-spin']),'id'=>'INTERNAL-DEMO-HTTP-002'],
        ['method'=>'GET','uri'=>'/cms/internal-games/dice/snapshot','name'=>'cms.internalGames.dice.snapshot','controller'=>'App\\Http\\Controllers\\Cms\\Front\\DiceController@snapshot','middleware'=>array_merge($web,['throttle:internal-games-snapshot']),'id'=>'INTERNAL-DEMO-HTTP-003'],
    ],
    'views'=>[['view_name'=>'game-provider-front.internal-dice','path'=>'resources/views/game-provider-front/internal-dice.blade.php']],
];
