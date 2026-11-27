<?php

namespace App\Http\Controllers\Cms\Front;

use App\Http\Controllers\Controller;
use App\Models\{CmsGame, InternalGameSession};
use App\Services\InternalGames\{DiceService, InternalGameCatalog, InternalGameDemoModeService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DiceController extends Controller
{
    public function play(string $sessionToken, DiceService $dice)
    {
        abort_unless(\Illuminate\Support\Str::isUuid($sessionToken), 404);
        $session=app(\App\Services\InternalGames\InternalGameSessionService::class)->resolvePlayableSession($sessionToken,auth('cms')->user());
        return $this->surface($session, $dice);
    }

    public function preview(CmsGame $game)
    {
        abort_unless(auth('cms')->guest(), 403);
        $catalog = app(InternalGameCatalog::class);
        $catalog->assertAllowed($game, null);
        abort_unless(app(InternalGameDemoModeService::class)->isInternalDemoSupported($game), 503);

        // Use the original view dispatch with an in-memory descriptor only.
        $preview = (new InternalGameSession())->forceFill(['game_code' => $catalog->extractGameCode($game), 'is_demo' => false]);
        $preview->setRelation('game', $game);

        return $this->surface($preview, app(DiceService::class));
    }

    private function surface(InternalGameSession $session, DiceService $dice)
    {
        $blackjack=app(\App\Services\InternalGames\BlackjackService::class);
        if($blackjack->supportsGameCode($session->game_code))return response()->view($blackjack->surfaceViewForSession($session),['session'=>$session,'payload'=>$blackjack->playPayload($session)])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
        $crash=app(\App\Services\InternalGames\SkyRushService::class);
        if($crash->supportsGameCode($session->game_code))return response()->view($crash->surfaceViewForSession($session),['session'=>$session,'payload'=>$crash->playPayload($session)])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
        $slots=app(\App\Services\InternalGames\InternalGamePlayService::class);
        if($slots->supportsGameCode($session->game_code))return response()->view($slots->surfaceViewForSession($session),['session'=>$session,'payload'=>$slots->playPayload($session)])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
        $roulette=$session->game_code!=='dice';
        $payload=$roulette?app(\App\Services\InternalGames\RouletteService::class)->playPayload($session):$dice->playPayload($session,auth('cms')->user());
        return response()->view($roulette?'game-provider-front.internal-roulette':'game-provider-front.internal-dice', ['payload'=>$payload])
            ->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }

    public function roll(Request $request, DiceService $dice)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sessionToken' => ['required', 'uuid'],
                'requestKey' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{8,100}\z/'],
                'betAmount' => ['required', 'numeric', 'regex:/\A\d{1,6}(\.\d{1,2})?\z/'],
                'target' => ['required', 'numeric', 'regex:/\A\d{1,2}(\.\d{1,2})?\z/'],
                'mode' => ['required', 'in:under,over'],
                'isDemo' => ['prohibited'], 'is_demo' => ['prohibited'], 'playType' => ['prohibited'],
                'currency' => ['prohibited'], 'wallet' => ['prohibited'], 'wallet_account_id' => ['prohibited'],
                'balance' => ['prohibited'], 'result' => ['prohibited'],
            ]);
            abort_if($validator->fails(), 422, trans('dice.invalid'));
            $data = $validator->validated();

            return response()->json(['status' => 200, 'data' => $dice->roll($data['sessionToken'], auth('cms')->user(),
                \Brick\Math\BigDecimal::of((string) $data['betAmount'])->multipliedBy(100)->toBigInteger()->toInt(), $data['mode'],
                \Brick\Math\BigDecimal::of((string) $data['target'])->multipliedBy(100)->toBigInteger()->toInt(), $data['requestKey'])])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
        } catch (HttpException $exception) {
            return response()->json(['status' => $exception->getStatusCode(),
                'message' => $exception->getMessage() ?: trans('dice.error')], $exception->getStatusCode())->header('Cache-Control','private, no-store');
        }
    }

    public function snapshot(Request $request, DiceService $dice)
    {
        abort_unless(is_string($request->query('sessionToken')) && \Illuminate\Support\Str::isUuid($request->query('sessionToken')), 422);

        return response()->json(['status' => 200, 'data' => $dice->snapshot($request->query('sessionToken'), auth('cms')->user())])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }
}
