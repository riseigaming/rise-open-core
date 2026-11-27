<?php
namespace App\Betting\Support;
use App\Betting\Application\Offers\CurrentOffersReadService;
use App\Betting\Acceptance\BettingAcceptanceSnapshotReadModel;
use App\Betting\Acceptance\StakeLimitPolicy;
use App\Betting\Bahisbot\BahisbotResearchService;
use App\Betting\Feed\InternalScraper\InternalScraperPublicLiveStateService;
use App\Betting\Support\DisplayTimezoneResolver;
use App\Betting\Support\BettingPublicSurfaceTierService;
use App\Betting\Support\PublicSelectionTokenService;
use App\Betting\Support\SportsbookAssetResolver;
use App\Betting\Support\SportsbookCatalogService;
use App\Betting\Support\SportsbookLeagueFilterKey;
use App\Betting\Support\SportsbookLocalParityService;
use App\Betting\Predictions\PredictionReadService;
use App\Betting\SocialMarkets\SocialMarketReadService;
use App\Models\BettingAcceptance;
use App\Models\BettingEvent;
use App\Models\CmsSiteSetting;
use App\Models\CmsUser;
use App\Http\Controllers\Controller;
use App\Services\OpsRadar\OpsRadarEventEmitter;
use App\Services\Localization\ProductLocaleResolver;
use App\Services\Social\SportsbookBetFeedService;
use App\Services\Wallet\ActiveWalletService;
use App\Services\Wallet\ProductWalletCapabilityService;
use App\Support\CmsThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
/** Existing frozen SportsbookShellController presentation methods, composed into Global. */
final class SportsbookShellPresentation
{
    private const LIVE_OFFERS_SEARCH_MAX_LENGTH = 120;
    private const LIVE_OFFERS_PAGE_SIZE = 150;
    private const COMPANION_OFFER_PAGE_SIZE = 8;
    private const NEXT_MATCHES_PAGE_SIZE = 24;
    private const FEATURED_MATCHES_PAGE_SIZE = 24;
    private const INITIAL_RENDER_OFFER_PAGE_SIZE = 24;
    private const OFFER_SUMMARY_UPCOMING_WINDOW_HOURS = 24;
    private const OFFER_SUMMARY_UPCOMING_DISPLAY_LIMIT = 50;
    private const SPORT_RAIL_PRODUCT_PRIORITY = [
        'soccer' => 10,
        'basketball' => 20,
        'tennis' => 30,
        'baseball' => 40,
    ];
    private const INITIAL_SURFACE_CACHE_SECONDS = 15;
    private const ADAPTER_VPS_OFFER_SUMMARY_CACHE_SECONDS = 20;
    private const OFFER_SUMMARY_CACHE_SECONDS = 45;
    private const OFFER_SUMMARY_HEALTHY_CACHE_TTL_SECONDS = 21600;
    private const OFFER_SUMMARY_TOTAL_GUARD_MIN_PREVIOUS = 80;
    private const OFFER_SUMMARY_PHASE_GUARD_MIN_PREVIOUS = 24;
    private const OFFER_SUMMARY_SPORT_GUARD_MIN_PREVIOUS = 18;
    private const OFFER_SUMMARY_DROP_RATIO = 0.20;
    private const PREDICTION_ITEMS_MAX_LIMIT = 18;
    private const SEARCH_ALIAS_MAP = [
        'amerika birlesik devletleri' => 'usa',
        'yeni zelanda' => 'new zealand',
        'guney afrika' => 'south africa',
        'guney kore' => 'south korea',
        'kuzey kore' => 'north korea',
        'cek cumhuriyeti' => 'czech republic',
        'turkiye' => 'turkey',
        'ingiltere' => 'england',
        'ispanya' => 'spain',
        'italya' => 'italy',
        'almanya' => 'germany',
        'fransa' => 'france',
        'portekiz' => 'portugal',
        'danimarka' => 'denmark',
        'macaristan' => 'hungary',
        'hirvatistan' => 'croatia',
        'bulgaristan' => 'bulgaria',
        'hollanda' => 'netherlands',
        'iskocya' => 'scotland',
        'norvec' => 'norway',
        'finlandiya' => 'finland',
        'irlanda' => 'ireland',
        'belcika' => 'belgium',
        'avusturya' => 'austria',
        'polonya' => 'poland',
        'isvicre' => 'switzerland',
        'yunanistan' => 'greece',
        'ukrayna' => 'ukraine',
        'rusya' => 'russia',
        'romanya' => 'romania',
        'sirbistan' => 'serbia',
        'slovakya' => 'slovakia',
        'slovenya' => 'slovenia',
        'litvanya' => 'lithuania',
        'letonya' => 'latvia',
        'estonya' => 'estonia',
        'izlanda' => 'iceland',
        'amerika' => 'usa',
        'abd' => 'usa',
    ];


 public function __construct(private DisplayTimezoneResolver $displayTimezoneResolver,private PublicSelectionTokenService $selectionTokenService,
  private BettingPublicSurfaceTierService $publicSurfaceTiers,private SportsbookCatalogService $catalog,private SportsbookAssetResolver $assetResolver) {}
 public function board(array $events):array {
  $rows=collect($events)->map(fn($e)=>$this->eventRow($e))->filter()->values();
  $leagues=$this->leagueBlocks($rows);$filters=$this->leagueFilters($leagues);
  $sports=[];foreach($rows as $row){$k=$row['sport_group_key'];$sports[$k]??=['label'=>$row['sport_label'],'pre_match_count'=>0,'in_game_count'=>0];$sports[$k][$row['phase_key']==='in-game'?'in_game_count':'pre_match_count']++;}
  $leagueStats=[];foreach($rows as $row){$k=$row['league_filter_key'];$leagueStats[$k]??=['label'=>$row['league_title'],'league_filter_key'=>$k,'sport_group_key'=>$row['sport_group_key'],'pre_match_count'=>0,'in_game_count'=>0,'event_count'=>0];$leagueStats[$k][$row['phase_key']==='in-game'?'in_game_count':'pre_match_count']++;$leagueStats[$k]['event_count']++;}
  $summary=['tab_counts'=>['pre-match'=>$rows->where('phase_key','pre-match')->count(),'in-game'=>$rows->where('phase_key','in-game')->count()],'sports'=>$sports,'leagues'=>$leagueStats,'total_events'=>$rows->count()];
  return ['leagues'=>$leagues,'leagueFilters'=>$filters,'leagueFilterGroups'=>$this->leagueFilterGroups($filters),'sports'=>$this->sportsStrip($summary),'rows'=>$rows->all(),'offerSummary'=>$summary];
 }
    private function adapterSelectionToken(array $event, array $market, array $selection, bool $selectable): ?string
    {
        if (!$selectable || (string) ($selection['acceptance_mode'] ?? 'legacy_quote_selection') !== 'adapter_vps_quote_ref') {
            return null;
        }

        return $this->selectionTokenService->issueForAdapterSelection($event, $market, $selection);
    }


    private function catalogMarketPresentationName(array $market, ?array $event, bool $family = false, string $fallback = ''): string
    {
        $original = $family
            ? (string) ($market['market_family_label'] ?? $market['display_name'] ?? '')
            : (string) ($market['display_name'] ?? '');
        $catalogType = (string) ($market['catalog_market_type'] ?? '');
        if ($catalogType === '') {
            return $original !== '' ? $original : ($fallback !== '' ? $fallback : (string) trans('cms_public.sportsbook.js.market_fallback'));
        }

        $sport = $this->sportGroupKey((string) data_get($event, 'sport_code', ''));
        $key = match ($catalogType) {
            'main_markets' => 'markets.main_chip',
            'quick_markets' => 'markets.quick',
            'match_result' => $sport === 'basketball' ? 'markets.team_wins' : 'markets.match_result',
            'match_winner' => 'markets.match_winner',
            'double_chance' => 'markets.double_chance',
            'total_goals' => 'markets.total',
            'total_points' => 'markets.total_points',
            'total_games' => 'markets.total_games',
            'spreads' => $sport === 'tennis' ? 'js.game_handicap' : 'markets.handicap',
            'game_handicap' => 'js.game_handicap',
            'both_teams_to_score' => 'markets.btts',
            'first_half_result' => 'markets.first_half',
            'half_time_full_time' => 'markets.half_time_full_time',
            'correct_score' => 'markets.correct_score',
            'correct_match_score' => 'markets.correct_match_score',
            'goals' => 'markets.goals',
            'home_team_markets' => 'markets.home_team',
            'away_team_markets' => 'markets.away_team',
            'combo_markets' => 'js.combo',
            'interval_markets' => 'markets.intervals',
            'corners_total' => 'markets.corners',
            'cards_total' => 'markets.cards',
            'player_markets' => 'markets.player_markets',
            'quarter_markets' => 'markets.quarters',
            'match_half' => 'markets.match_half',
            'team_totals' => 'markets.team_total',
            'winning_margin' => 'markets.winning_margin',
            'set_winner' => 'markets.sets',
            'first_set_winner' => 'markets.first_set',
            'set_score' => 'markets.set_score',
            'tie_break' => 'markets.tie_break',
            'points_markets' => 'markets.points',
            'games_markets' => 'markets.games',
            default => null,
        };
        if ($key === null) {
            return $original !== '' ? $original : ($fallback !== '' ? $fallback : (string) trans('cms_public.sportsbook.js.market_fallback'));
        }

        $label = (string) trans('cms_public.sportsbook.' . $key);
        $line = $market['line'] ?? null;
        if (!$family && (string) ($market['market_type'] ?? '') === 'spreads' && is_numeric($line)) {
            $label .= ' ' . $this->formatSignedLine((string) $line);
        }

        return $label;
    }


    private function catalogShellSelectionItem(array $market, array $selection, ?array $event = null): array
    {
        $message = (string) trans('cms_public.sportsbook.market_unavailable');

        return [
            'label' => $message,
            'raw_odd' => '',
            'odd' => '-',
            'quote_selection_id' => null,
            'acceptance_mode' => 'catalog_shell',
            'selection_token' => null,
            'quote_version_id' => null,
            'event_id' => data_get($event, 'event_id'),
            'market_type' => (string) ($market['market_type'] ?? ''),
            'market_specifier_key' => (string) ($market['specifier_key'] ?? 'catalog_shell'),
            'outcome_code' => (string) ($selection['outcome_code'] ?? 'catalog_shell'),
            'market_name' => $this->catalogMarketPresentationName($market, $event),
            'outcome_name' => $message,
            'event_name' => data_get($event, 'canonical_name'),
            'selection_key' => 'catalog-shell-' . data_get($event, 'event_id') . '-' . ($market['market_type'] ?? 'market'),
            'offer_status' => 'unavailable',
            'bettable_reason' => (string) ($selection['bettable_reason'] ?? $market['bettable_reason'] ?? 'odds_not_available'),
            'selectable' => false,
            'acceptance_ready' => false,
            'disabled' => true,
            'is_catalog_shell' => true,
            'selection_state_message' => $message,
        ];
    }


    private function compareLeagueFilterGroups(array $left, array $right): int
    {
        $leftSport = (string) ($left['sport_group_key'] ?? 'all');
        $rightSport = (string) ($right['sport_group_key'] ?? 'all');

        if ($leftSport === 'soccer' && $rightSport === 'soccer') {
            $groupComparison = ((int) ($left['rail_group_priority'] ?? PHP_INT_MAX)) <=> ((int) ($right['rail_group_priority'] ?? PHP_INT_MAX));
            if ($groupComparison !== 0) {
                return $groupComparison;
            }

            $surfaceComparison = ((int) ($left['public_surface_rank'] ?? PHP_INT_MAX)) <=> ((int) ($right['public_surface_rank'] ?? PHP_INT_MAX));
            if ($surfaceComparison !== 0) {
                return $surfaceComparison;
            }

            $countComparison = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        }

        $sportComparison = $this->sportRailProductPriority($leftSport) <=> $this->sportRailProductPriority($rightSport);
        if ($sportComparison !== 0) {
            return $sportComparison;
        }

        $priorityComparison = ((int) ($right['priority_score'] ?? 0)) <=> ((int) ($left['priority_score'] ?? 0));
        if ($priorityComparison !== 0) {
            return $priorityComparison;
        }

        $countComparison = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));
        if ($countComparison !== 0) {
            return $countComparison;
        }

        return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    }


    private function compareLeagueFilterItems(array $left, array $right): int
    {
        $leftSport = (string) ($left['sport_group_key'] ?? 'all');
        $rightSport = (string) ($right['sport_group_key'] ?? 'all');

        if ($leftSport === 'soccer' && $rightSport === 'soccer') {
            foreach (['rail_group_priority', 'rail_item_priority', 'rail_region_priority'] as $key) {
                $comparison = ((int) ($left[$key] ?? PHP_INT_MAX)) <=> ((int) ($right[$key] ?? PHP_INT_MAX));
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            $surfaceComparison = ((int) ($left['public_surface_rank'] ?? PHP_INT_MAX)) <=> ((int) ($right['public_surface_rank'] ?? PHP_INT_MAX));
            if ($surfaceComparison !== 0) {
                return $surfaceComparison;
            }

            $countComparison = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        }

        $sportComparison = $this->sportRailProductPriority($leftSport) <=> $this->sportRailProductPriority($rightSport);
        if ($sportComparison !== 0) {
            return $sportComparison;
        }

        $priorityComparison = ((int) ($right['priority_score'] ?? 0)) <=> ((int) ($left['priority_score'] ?? 0));
        if ($priorityComparison !== 0) {
            return $priorityComparison;
        }

        $countComparison = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));
        if ($countComparison !== 0) {
            return $countComparison;
        }

        return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    }


    private function competitionAssetDescriptor(array $payload, string $label): array
    {
        $canonicalKey = (string) (
            data_get($payload, 'canonical_competition_key')
            ?: data_get($payload, 'competition_canonical_key')
            ?: data_get($payload, 'competition_key')
            ?: ''
        );
        $definition = $canonicalKey !== '' ? $this->catalog->competition($canonicalKey) : null;

        return $this->assetResolver->competition($canonicalKey, $definition ?: ['label' => $label]);
    }


    private function eventPhaseKey(array $event, ?Carbon $startsAt): string
    {
        $publicState = data_get($event, 'public_live_state');
        if (is_array($publicState) && array_key_exists('phase_key', $publicState)) {
            return (string) $publicState['phase_key'];
        }

        $lifecycleStatus = (string) ($event['lifecycle_status'] ?? '');
        $isLive = (bool) ($event['is_live'] ?? false);

        if (in_array($lifecycleStatus, ['settled', 'cancelled', 'finished', 'completed', 'closed'], true)) {
            return 'ended';
        }

        if ($isLive || in_array($lifecycleStatus, ['started', 'live', 'inplay'], true)) {
            return 'in-game';
        }

        return 'pre-match';
    }


    private function eventRow(array $event, array $options = []): ?array
    {
        $initialSurface = in_array((string) ($options['surface'] ?? ''), ['initial', 'companion'], true);
        $markets = collect(data_get($event, 'quote.markets', []));
        $matchResultMarket = $markets->firstWhere('market_type', 'match_result');
        $totalsMarket = $markets->first(function (array $market) {
            return in_array($market['market_type'], ['total_goals', 'total_points'], true);
        });

        $matchResultSelections = $this->mapMatchResultSelections($matchResultMarket, $event);
        $totalsSelections = $initialSurface ? [] : $this->mapTotalsSelections($totalsMarket, $event);
        $extraMarkets = $initialSurface ? [] : $this->mapExtraMarkets($markets, $event);
        $lazyMarketCount = $initialSurface
            ? $this->initialSurfaceLazyMarketCount($markets, $totalsMarket)
            : count($extraMarkets);

        if (empty($matchResultSelections) && empty($totalsSelections) && empty($extraMarkets) && $lazyMarketCount <= 0) {
            return null;
        }

        $startsAt = $this->displayTimezoneResolver->canonicalStartsAt($event['starts_at'] ?? null);
        $displayTimezone = $this->displayTimezoneResolver->resolve(request(), auth()->guard('cms')->user());
        $displayStartAt = $this->displayTimezoneResolver->formatStartsAt($startsAt ?? ($event['starts_at'] ?? null), $displayTimezone);
        $remainingMarketCount = $lazyMarketCount;
        $phaseKey = (string) data_get($event, 'public_live_state.phase_key', $this->eventPhaseKey($event, $startsAt));
        $tradingStatus = strtolower((string) ($event['trading_status'] ?? ''));
        $hasHydratingContract = collect($matchResultSelections)
            ->contains(fn (array $selection): bool => ($selection['contract_status'] ?? '') === 'hydrating');
        $rowNoticeState = match (true) {
            $phaseKey === 'ended' => 'ended',
            in_array($tradingStatus, ['closed', 'settled', 'cancelled'], true) => 'closed',
            in_array($tradingStatus, ['suspended', 'paused'], true) => 'suspended',
            (bool) data_get($event, 'quote.is_tradeable', false) => 'none',
            $hasHydratingContract => 'resolving',
            default => 'unavailable',
        };

        $sportGroupKey = $this->sportGroupKey((string) $event['sport_code']);
        $rawLeagueTitle = (string) ($event['competition_label'] ?? $event['tournament_name'] ?? $this->sportLabel((string) $event['sport_code']));
        $competitionGroupKey = (string) ($event['competition_group_key'] ?? $event['sport_code'] . '|' . Str::slug($rawLeagueTitle, '_'));
        $leagueFilterKey = (string) ($event['league_filter_key'] ?? SportsbookLeagueFilterKey::fromLabel($sportGroupKey, $rawLeagueTitle));
        $railMeta = $this->leagueRailMeta([
            'title' => $rawLeagueTitle,
            'sport_group_key' => $sportGroupKey,
            'league_filter_key' => $leagueFilterKey,
            'competition_group_key' => $competitionGroupKey,
            'competition_canonical_key' => (string) data_get($event, 'competition_canonical_key', ''),
            'canonical_competition_key' => (string) data_get($event, 'canonical_competition_key', ''),
            'competition_country_key' => (string) data_get($event, 'competition_country_key', ''),
            'competition_country_label' => (string) data_get($event, 'competition_country_label', ''),
            'competition_identity_known' => (bool) data_get($event, 'competition_identity_known', false),
            'competition_identity_tier' => (string) data_get($event, 'competition_identity_tier', ''),
        ]);
        $leagueTitle = $rawLeagueTitle;
        $homeTeamCanonicalKey = (string) data_get($event, 'home_team_canonical_key', '');
        $awayTeamCanonicalKey = (string) data_get($event, 'away_team_canonical_key', '');
        $homeLabel = (string) ($event['participant_home'] ?? '');
        $awayLabel = (string) ($event['participant_away'] ?? '');

        $row = [
            'event_id' => $event['event_id'],
            'event_count' => max(1, (int) ($event['event_count'] ?? 1)),
            'starts_at_timestamp' => $startsAt?->getTimestamp() ?? PHP_INT_MAX,
            'league_title' => $leagueTitle,
            'league_filter_key' => $leagueFilterKey,
            'league_group_key' => $railMeta['group_key'],
            'league_group_label' => $railMeta['group_label'],
            'rail_group_priority' => $railMeta['group_priority'],
            'rail_item_priority' => $railMeta['item_priority'],
            'rail_region_priority' => $railMeta['region_priority'],
            'competition_group_key' => $competitionGroupKey,
            'competition_priority_score' => (int) data_get($event, 'competition_priority_score', PHP_INT_MAX),
            'competition_priority_bucket' => (string) data_get($event, 'competition_priority_bucket', 'normal'),
            'competition_canonical_key' => (string) data_get($event, 'competition_canonical_key', ''),
            'canonical_competition_key' => (string) data_get($event, 'canonical_competition_key', ''),
            'competition_country_key' => (string) data_get($event, 'competition_country_key', ''),
            'competition_country_label' => (string) data_get($event, 'competition_country_label', ''),
            'competition_identity_known' => (bool) data_get($event, 'competition_identity_known', false),
            'competition_identity_tier' => (string) data_get($event, 'competition_identity_tier', ''),
            'competition_asset' => $this->shellAssetDescriptor($this->competitionAssetDescriptor($event, $leagueTitle)),
            'public_surface_tier' => (string) $railMeta['public_surface_tier'],
            'public_surface_rank' => (int) $railMeta['public_surface_rank'],
            'competition_sport_priority' => (int) data_get($event, 'competition_sport_priority', PHP_INT_MAX),
            'league_badge' => $this->leagueBadge((string) $leagueTitle),
            'sport_label' => $this->sportLabel((string) $event['sport_code']),
            'sport_group_key' => $sportGroupKey,
            'sport_asset' => $this->shellAssetDescriptor(
                $this->assetResolver->sport($sportGroupKey, $this->sportLabel((string) $event['sport_code']))
            ),
            'phase_key' => $phaseKey,
            'phase_label' => $phaseKey === 'in-game'
                ? (string) trans('cms_public.sportsbook.js.phase_live')
                : (string) trans('cms_public.sportsbook.tabs.pre_match'),
            'lifecycle_status' => (string) ($event['lifecycle_status'] ?? ''),
            'trading_status' => (string) ($event['trading_status'] ?? ''),
            'event_name' => $event['canonical_name'],
            'front_betting_enabled' => (bool) data_get($event, 'front_betting_enabled', true),
            'is_tradeable' => (bool) data_get($event, 'quote.is_tradeable', false),
            'row_notice_state' => $rowNoticeState,
            'time' => $displayStartAt['time'],
            'date' => $displayStartAt['date'],
            'home' => $homeLabel,
            'away' => $awayLabel,
            'home_team_canonical_key' => $homeTeamCanonicalKey,
            'away_team_canonical_key' => $awayTeamCanonicalKey,
            'home_team_asset' => $this->shellAssetDescriptor($this->teamAssetDescriptor($homeTeamCanonicalKey, $homeLabel)),
            'away_team_asset' => $this->shellAssetDescriptor($this->teamAssetDescriptor($awayTeamCanonicalKey, $awayLabel)),
            'match_result' => $matchResultSelections,
            'totals' => $totalsSelections,
            'extra_markets' => $extraMarkets,
            'extra_market_count' => $remainingMarketCount,
            'markets_lazy' => $initialSurface && $remainingMarketCount > 0,
            'more' => '+' . $remainingMarketCount,
            'display_timezone' => $displayTimezone,
            'starts_at_iso' => $displayStartAt['iso'],
        ];
        if (!empty($event['offline_demo'])) {
            $decorate = function (array $value) use (&$decorate): array {
                foreach ($value as $key => $item) {
                    if (is_array($item)) $value[$key] = $decorate($item);
                }
                if (isset($value['raw_odd'])) {
                    $value['acceptance_mode'] = 'offline_demo';
                    $value['demo_selectable'] = is_numeric($value['raw_odd']) && (float) $value['raw_odd'] > 1;
                    $value['selectable'] = false;
                    $value['acceptance_ready'] = false;
                    $value['selection_token'] = null;
                    $value['quote_selection_id'] = null;
                    $value['quote_version_id'] = null;
                    $value['contract_status'] = 'offline_demo';
                }
                return $value;
            };
            $row = $decorate($row);
            $row['offline_demo'] = true;
            $row['row_notice_state'] = 'none';
        }
        return $row;
    }


    private function extraMarketOutcomeName(array $market, array $selection, ?array $event = null): string
    {
        $marketType = (string) ($market['market_type'] ?? '');

        if (in_array($marketType, ['total_goals', 'total_points', 'total_games'], true)) {
            $label = $this->totalOutcomePresentationName($market, $selection);
            if ($label !== null) {
                return $label;
            }
        }

        if ($marketType === 'both_teams_to_score') {
            return match ((string) ($selection['outcome_code'] ?? '')) {
                'yes' => (string) trans('cms_public.sportsbook.markets.btts_yes'),
                'no' => (string) trans('cms_public.sportsbook.markets.btts_no'),
                default => (string) ($selection['display_name'] ?? $selection['outcome_code'] ?? ''),
            };
        }

        if ($marketType === 'spreads') {
            $displayName = trim((string) ($selection['display_name'] ?? ''));
            if ($displayName !== '' && preg_match('/[+-]?\d+(?:[.,]\d+)?$/', $displayName) === 1) {
                return $displayName;
            }

            $outcomeCode = (string) ($selection['outcome_code'] ?? '');
            $teamName = match ($outcomeCode) {
                'home' => (string) data_get($event, 'home', ''),
                'away' => (string) data_get($event, 'away', ''),
                default => $displayName,
            };
            $rawLine = $selection['line'] ?? $selection['point'] ?? $market['line'] ?? $market['point'] ?? null;
            $line = is_numeric($rawLine) ? (float) $rawLine : null;

            if ($line !== null && $outcomeCode === 'away') {
                $line *= -1;
            }

            if ($teamName !== '' && $line !== null) {
                return trim($teamName . ' ' . $this->formatSignedLine($line));
            }

            if ($teamName !== '') {
                return $teamName;
            }
        }

        return (string) ($selection['display_name'] ?? $selection['outcome_code'] ?? '');
    }


    private function footballRailCompetitionAliases(): array
    {
        $aliases = [];
        $config = $this->footballRailConfig();
        $competitionKeys = array_unique(array_merge(
            array_keys((array) data_get($config, 'featured_competitions', [])),
            array_keys((array) data_get($config, 'labels', [])),
            array_keys((array) data_get($config, 'competition_regions', []))
        ));

        foreach ($competitionKeys as $competitionKey) {
            $canonical = $this->footballRailLookupKey((string) $competitionKey);
            if ($canonical === '') {
                continue;
            }

            $aliases[$canonical] = $canonical;
            $label = (string) data_get($config, 'labels.' . $competitionKey, data_get($config, 'featured_competitions.' . $competitionKey . '.label', ''));
            foreach (array_filter([$label, str_replace('_', ' ', (string) $competitionKey)]) as $alias) {
                $aliases[$this->footballRailLookupKey((string) $alias)] = $canonical;
            }

            foreach ((array) data_get($config, 'featured_competitions.' . $competitionKey . '.aliases', []) as $alias) {
                $aliases[$this->footballRailLookupKey((string) $alias)] = $canonical;
            }
        }

        foreach ((array) data_get($config, 'aliases', []) as $alias => $canonicalKey) {
            $normalizedAlias = $this->footballRailLookupKey((string) $alias);
            $normalizedCanonical = $this->footballRailLookupKey((string) $canonicalKey);
            if ($normalizedAlias !== '' && $normalizedCanonical !== '') {
                $aliases[$normalizedAlias] = $normalizedCanonical;
            }
        }

        return array_filter($aliases, fn (string $key): bool => $key !== '', ARRAY_FILTER_USE_KEY);
    }


    private function footballRailCompetitionKey(array $league): string
    {
        $aliases = $this->footballRailCompetitionAliases();
        $candidates = [
            (string) ($league['canonical_competition_key'] ?? ''),
            (string) ($league['competition_canonical_key'] ?? ''),
            (string) ($league['competition_key'] ?? ''),
            (string) ($league['competition_group_key'] ?? ''),
            (string) ($league['league_filter_key'] ?? ''),
            (string) ($league['title'] ?? ''),
            (string) ($league['label'] ?? ''),
            (string) ($league['league_title'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            foreach ($this->footballRailLookupCandidates($candidate) as $lookup) {
                if (isset($aliases[$lookup])) {
                    return $aliases[$lookup];
                }
            }
        }

        foreach ($candidates as $candidate) {
            $lookup = $this->footballRailLookupKey($candidate);
            if ($lookup !== '') {
                return $lookup;
            }
        }

        return '';
    }


    private function footballRailConfig(): array
    {
        return (array) config('betting.sportsbook_frontend.football_league_rail', []);
    }


    private function footballRailItemPriority(string $competitionKey): int
    {
        if ($competitionKey === '') {
            return PHP_INT_MAX;
        }

        $featuredPriority = data_get($this->footballRailConfig(), 'featured_competitions.' . $competitionKey . '.priority');
        if ($featuredPriority !== null) {
            return (int) $featuredPriority;
        }

        return match ($competitionKey) {
            'brazil_serie_a' => 300,
            'brazil_serie_b' => 320,
            'spain_segunda' => 330,
            'sweden_allsvenskan' => 340,
            'sweden_superettan' => 350,
            'finland_veikkausliiga' => 360,
            'norway_eliteserien' => 370,
            'chile_primera' => 380,
            default => 500,
        };
    }


    private function footballRailLabel(string $fallbackTitle, string $competitionKey): string
    {
        $configured = (string) data_get($this->footballRailConfig(), 'labels.' . $competitionKey, '');
        if ($configured !== '') {
            return $configured;
        }

        $label = trim(preg_replace('/\s+/u', ' ', $fallbackTitle) ?? $fallbackTitle);

        return $label !== '' ? $label : $this->tournamentFallbackLabel();
    }


    private function footballRailLookupCandidates(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $scopedCandidates = [];
        if (substr_count($value, ':') >= 2) {
            $parts = array_values(array_filter(explode(':', $value), static fn (string $part): bool => trim($part) !== ''));
            $country = (string) ($parts[1] ?? '');
            $competition = (string) ($parts[2] ?? '');

            if ($country !== '' && $competition !== '') {
                $scopedCandidates[] = $country . '_' . $competition;
            }

            if ($competition !== '') {
                $scopedCandidates[] = $competition;
            }
        }

        return collect([
            $value,
            str_contains($value, '|') ? (string) Str::afterLast($value, '|') : '',
            str_contains($value, '--') ? (string) Str::afterLast($value, '--') : '',
            str_replace([' - ', '-', '/'], ' ', $value),
        ])
            ->concat($scopedCandidates)
            ->map(fn (string $candidate): string => $this->footballRailLookupKey($candidate))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }


    private function footballRailLookupKey(string $value): string
    {
        return trim(Str::slug(Str::ascii(trim($value)), '_'));
    }


    private function footballRailRegionKey(array $league, string $competitionKey, string $title): string
    {
        $configured = (string) data_get($this->footballRailConfig(), 'competition_regions.' . $competitionKey, '');
        if ($configured !== '') {
            return $configured;
        }

        $countryKey = $this->footballRailLookupKey((string) ($league['competition_country_key'] ?? ''));
        $countryLabelKey = $this->footballRailLookupKey((string) ($league['competition_country_label'] ?? ''));
        $titleKey = $this->footballRailLookupKey($title);
        $haystack = implode('_', array_filter([$competitionKey, $countryKey, $countryLabelKey, $titleKey]));

        foreach (['brazil', 'england', 'spain', 'germany', 'france', 'turkey', 'usa', 'japan', 'sweden', 'finland', 'norway', 'chile'] as $regionKey) {
            if (str_contains($haystack, $regionKey)) {
                return $regionKey;
            }
        }

        if (str_contains($haystack, 'uefa') || str_contains($haystack, 'europe')) {
            return 'europe';
        }

        if (str_contains($haystack, 'fifa') || str_contains($haystack, 'international') || str_contains($haystack, 'world')) {
            return 'international';
        }

        if (str_contains($haystack, 'conmebol') || str_contains($haystack, 'libertadores') || str_contains($haystack, 'sudamericana')) {
            return 'south_america';
        }

        return 'other';
    }


    private function formatLine(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }


    private function formatOdd(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }


    private function formatSignedLine(float $value): string
    {
        $formatted = $this->formatLine((string) abs($value));

        if ($value > 0) {
            return '+' . $formatted;
        }

        if ($value < 0) {
            return '-' . $formatted;
        }

        return $formatted;
    }


    private function hasInitialSurfaceRenderableTotals(?array $market): bool
    {
        if (!$market) {
            return false;
        }

        $selections = collect($market['selections'] ?? [])->keyBy('outcome_code');

        return $selections->has('under') || $selections->has('over');
    }


    private function initialSurfaceLazyMarketCount(Collection $markets, ?array $totalsMarket): int
    {
        $totalsCount = $this->hasInitialSurfaceRenderableTotals($totalsMarket) ? 1 : 0;
        $extraCount = $markets
            ->reject(function (array $market) {
                return in_array($market['market_type'], ['match_result', 'total_goals', 'total_points'], true);
            })
            ->filter(function (array $market): bool {
                return collect($market['selections'] ?? [])
                    ->contains(fn (array $selection): bool => $this->shouldRenderExtraMarketSelection($market, $selection));
            })
            ->count();

        return $totalsCount + $extraCount;
    }


    private function isAdapterProviderSelection(?array $event, array $selection): bool
    {
        return str_starts_with((string) data_get($event, 'event_key', ''), 'adapter_vps:')
            || str_starts_with((string) ($selection['acceptance_mode'] ?? ''), 'adapter_vps_');
    }


    private function isPublicEventBeforeAcceptanceCutoff(array $event): bool
    {
        $startsAt = $this->publicEventStartsAt($event);
        if (!$startsAt instanceof Carbon) {
            return true;
        }

        $cutoffSeconds = max(0, (int) config('betting.acceptance.cutoff_seconds_before_start', 900));

        return now()->lt($startsAt->copy()->subSeconds($cutoffSeconds));
    }


    private function isPublicSelectionOpenForAcceptance(?array $event, array $market, array $selection): bool
    {
        return $event !== null
            && $this->isPublicEventBeforeAcceptanceCutoff($event)
            && (bool) data_get($event, 'quote.is_tradeable', false)
            && (bool) ($selection['bettable'] ?? true)
            && (string) ($selection['offer_status'] ?? 'open') === 'open'
            && (string) ($market['status'] ?? 'open') === 'open';
    }


    private function leagueBadge(string $title): string
    {
        $letters = collect(preg_split('/[\s\.\-]+/u', trim($title)) ?: [])
            ->filter(fn (?string $part) => !empty($part))
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(3)
            ->implode('');

        return $letters !== '' ? $letters : 'LIG';
    }


    private function leagueBlocks(Collection $events): array
    {
        return $events
            ->groupBy(function (array $event) {
                return $event['sport_group_key'] . '|' . ($event['competition_group_key'] ?? $event['league_filter_key'] ?? $event['league_title']);
            })
            ->map(function (Collection $leagueEvents) {
                $first = $leagueEvents->first();
                $leagueTitle = (string) ($first['competition_label'] ?? $first['league_title'] ?? 'Lig');
                $leagueFilterKey = (string) ($first['league_filter_key']
                    ?? SportsbookLeagueFilterKey::fromLabel((string) ($first['sport_group_key'] ?? 'all'), $leagueTitle));

                return [
                    'badge' => $this->leagueBadge($leagueTitle),
                    'title' => $leagueTitle,
                    'sport' => $first['sport_label'],
                    'sport_group_key' => $first['sport_group_key'],
                    'league_filter_key' => $leagueFilterKey,
                    'competition_group_key' => (string) ($first['competition_group_key'] ?? ''),
                    'competition_priority_score' => (int) ($first['competition_priority_score'] ?? PHP_INT_MAX),
                    'competition_priority_bucket' => (string) ($first['competition_priority_bucket'] ?? 'normal'),
                    'competition_canonical_key' => (string) ($first['competition_canonical_key'] ?? ''),
                    'canonical_competition_key' => (string) ($first['canonical_competition_key'] ?? ''),
                    'competition_country_label' => (string) ($first['competition_country_label'] ?? ''),
                    'competition_identity_known' => (bool) ($first['competition_identity_known'] ?? false),
                    'competition_identity_tier' => (string) ($first['competition_identity_tier'] ?? ''),
                    'competition_asset' => (array) ($first['competition_asset'] ?? []),
                    'public_surface_tier' => (string) ($first['public_surface_tier'] ?? 'standard_surface'),
                    'public_surface_rank' => (int) ($first['public_surface_rank'] ?? $this->publicSurfaceTiers->rank('standard_surface')),
                    'league_group_key' => (string) ($first['league_group_key'] ?? ''),
                    'league_group_label' => (string) ($first['league_group_label'] ?? ''),
                    'rail_group_priority' => (int) ($first['rail_group_priority'] ?? PHP_INT_MAX),
                    'rail_item_priority' => (int) ($first['rail_item_priority'] ?? PHP_INT_MAX),
                    'rail_region_priority' => (int) ($first['rail_region_priority'] ?? PHP_INT_MAX),
                    'match_count' => $leagueEvents->count(),
                    'pre_match_count' => (int) $leagueEvents->where('phase_key', 'pre-match')->count(),
                    'in_game_count' => (int) $leagueEvents->where('phase_key', 'in-game')->count(),
                    'nearest_starts_at_timestamp' => (int) $leagueEvents->min('starts_at_timestamp'),
                    'matches' => $leagueEvents
                        ->sortBy([
                            ['starts_at_timestamp', 'asc'],
                            ['event_id', 'asc'],
                        ])
                        ->map(function (array $event) {
                        return [
                            'event_id' => $event['event_id'],
                            'sport_group_key' => $event['sport_group_key'],
                            'event_name' => $event['event_name'],
                            'phase_key' => $event['phase_key'],
                            'phase_label' => $event['phase_label'],
                            'lifecycle_status' => $event['lifecycle_status'],
                            'trading_status' => $event['trading_status'],
                            'starts_at_timestamp' => $event['starts_at_timestamp'],
                            'front_betting_enabled' => $event['front_betting_enabled'],
                            'is_tradeable' => $event['is_tradeable'],
                            'time' => $event['time'],
                            'date' => $event['date'],
                            'home' => $event['home'],
                            'away' => $event['away'],
                            'home_team_canonical_key' => (string) ($event['home_team_canonical_key'] ?? ''),
                            'away_team_canonical_key' => (string) ($event['away_team_canonical_key'] ?? ''),
                            'home_team_asset' => (array) ($event['home_team_asset'] ?? []),
                            'away_team_asset' => (array) ($event['away_team_asset'] ?? []),
                            'offline_demo' => (bool)($event['offline_demo'] ?? false),
                            'match_result' => $event['match_result'],
                            'totals' => $event['totals'],
                            'extra_markets' => $event['extra_markets'],
                            'more' => $event['more'],
                        ];
                    })->values()->all(),
                ];
            })
            ->groupBy(fn (array $league) => ($league['sport_group_key'] ?? 'all') . '|' . ($league['league_filter_key'] ?? ''))
            ->map(function (Collection $leagueGroup) {
                $first = $leagueGroup->first();
                $matches = $leagueGroup
                    ->flatMap(fn (array $league) => (array) ($league['matches'] ?? []))
                    ->sortBy([
                        ['starts_at_timestamp', 'asc'],
                        ['event_id', 'asc'],
                    ])
                    ->values()
                    ->all();

                return [
                    'badge' => $first['badge'],
                    'title' => $first['title'],
                    'sport' => $first['sport'],
                    'sport_group_key' => $first['sport_group_key'],
                    'league_filter_key' => $first['league_filter_key'],
                    'competition_group_key' => (string) ($first['competition_group_key'] ?? ''),
                    'competition_priority_score' => (int) ($first['competition_priority_score'] ?? PHP_INT_MAX),
                    'competition_priority_bucket' => (string) ($first['competition_priority_bucket'] ?? 'normal'),
                    'competition_canonical_key' => (string) ($first['competition_canonical_key'] ?? ''),
                    'canonical_competition_key' => (string) ($first['canonical_competition_key'] ?? ''),
                    'competition_identity_known' => (bool) ($first['competition_identity_known'] ?? false),
                    'competition_identity_tier' => (string) ($first['competition_identity_tier'] ?? ''),
                    'competition_asset' => (array) ($first['competition_asset'] ?? []),
                    'public_surface_tier' => (string) ($first['public_surface_tier'] ?? 'standard_surface'),
                    'public_surface_rank' => (int) ($leagueGroup->min('public_surface_rank') ?? $this->publicSurfaceTiers->rank('standard_surface')),
                    'league_group_key' => (string) ($first['league_group_key'] ?? ''),
                    'league_group_label' => (string) ($first['league_group_label'] ?? ''),
                    'rail_group_priority' => (int) ($leagueGroup->min('rail_group_priority') ?? PHP_INT_MAX),
                    'rail_item_priority' => (int) ($leagueGroup->min('rail_item_priority') ?? PHP_INT_MAX),
                    'rail_region_priority' => (int) ($leagueGroup->min('rail_region_priority') ?? PHP_INT_MAX),
                    'match_count' => (int) $leagueGroup->sum('match_count'),
                    'pre_match_count' => (int) $leagueGroup->sum('pre_match_count'),
                    'in_game_count' => (int) $leagueGroup->sum('in_game_count'),
                    'nearest_starts_at_timestamp' => (int) $leagueGroup->min('nearest_starts_at_timestamp'),
                    'matches' => $matches,
                ];
            })
            ->sortBy([
                ['public_surface_rank', 'asc'],
                ['competition_priority_score', 'desc'],
                ['rail_group_priority', 'asc'],
                ['rail_item_priority', 'asc'],
                ['nearest_starts_at_timestamp', 'asc'],
                ['title', 'asc'],
            ])
            ->map(function (array $league) {
                unset($league['nearest_starts_at_timestamp']);

                return $league;
            })
            ->values()
            ->all();
    }


    private function leagueFilterGroupMeta(string $leagueTitle, string $sportGroupKey): array
    {
        $normalizedTitle = trim(preg_replace('/\s+/u', ' ', $leagueTitle)) ?: $this->tournamentsFallbackLabel();
        $delimitedParts = preg_split('/\s(?:-|\||\/|:)\s/u', $normalizedTitle, 2);
        $candidate = '';

        if (is_array($delimitedParts) && count($delimitedParts) === 2) {
            $candidate = trim((string) ($delimitedParts[0] ?? ''));
        } elseif (str_contains($normalizedTitle, '. ')) {
            $dotCandidate = trim(Str::before($normalizedTitle, '. '));
            if ($dotCandidate !== '' && mb_strlen($dotCandidate) <= 24) {
                $candidate = $dotCandidate;
            }
        }

        if ($candidate === '' && preg_match('/^(UEFA|FIFA|CONMEBOL|CONCACAF|AFC|CAF)\b/u', $normalizedTitle, $matches) === 1) {
            $candidate = (string) ($matches[1] ?? '');
        }

        if ($candidate === '' || mb_strlen($candidate) > 28) {
            $sportLabel = $this->sportGroupLabel($sportGroupKey);
            $candidate = $sportLabel === $this->tournamentFallbackLabel()
                ? $this->tournamentsFallbackLabel()
                : (string) trans('cms_public.sportsbook.filters.sport_leagues', ['sport' => $sportLabel]);
        }

        return [
            'key' => Str::slug($sportGroupKey . '-' . $candidate, '_'),
            'label' => $candidate,
        ];
    }


    private function leagueFilterGroups(array $leagueFilters): array
    {
        return collect($leagueFilters)
            ->groupBy(fn (array $item) => ($item['sport_group_key'] ?? 'all') . '|' . ($item['group_key'] ?? 'other'))
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'key' => (string) ($first['group_key'] ?? 'other'),
                    'label' => (string) ($first['group_label'] ?? $this->tournamentsFallbackLabel()),
                    'sport_group_key' => (string) ($first['sport_group_key'] ?? 'all'),
                    'priority_score' => (int) $group->max('priority_score'),
                    'rail_group_priority' => (int) ($group->min('rail_group_priority') ?? PHP_INT_MAX),
                    'rail_item_priority' => (int) ($group->min('rail_item_priority') ?? PHP_INT_MAX),
                    'rail_region_priority' => (int) ($group->min('rail_region_priority') ?? PHP_INT_MAX),
                    'public_surface_tier' => $this->publicSurfaceTierFromRank((int) ($group->min('public_surface_rank') ?? $this->publicSurfaceTiers->rank('long_tail_surface'))),
                    'public_surface_rank' => (int) ($group->min('public_surface_rank') ?? $this->publicSurfaceTiers->rank('long_tail_surface')),
                    'count' => (int) $group->sum('count'),
                    'event_count' => (int) $group->sum('count'),
                    'item_count' => $group->count(),
                    'items' => $group
                        ->sort(fn (array $left, array $right): int => $this->compareLeagueFilterItems($left, $right))
                        ->values()
                        ->all(),
                ];
            })
            ->sort(fn (array $left, array $right): int => $this->compareLeagueFilterGroups($left, $right))
            ->values()
            ->all();
    }


    private function leagueFilters(array $leagues, string $defaultTabKey = 'pre-match'): array
    {
        $activePhaseCountKey = $defaultTabKey === 'in-game' ? 'in_game_count' : 'pre_match_count';

        return collect($leagues)
            ->map(function (array $league) use ($activePhaseCountKey) {
                $sportGroupKey = (string) ($league['sport_group_key'] ?? 'all');
                $leagueKey = (string) ($league['league_filter_key']
                    ?? SportsbookLeagueFilterKey::fromLabel($sportGroupKey, (string) ($league['title'] ?? 'turnuva')));
                $railMeta = $this->leagueRailMeta($league + [
                    'league_filter_key' => $leagueKey,
                    'sport_group_key' => $sportGroupKey,
                ]);
                $priorityScore = (int) ($league['competition_priority_score'] ?? PHP_INT_MAX);
                $priorityBucket = (string) ($league['competition_priority_bucket'] ?? 'normal');

                return [
                    'key' => $leagueKey,
                    'label' => $railMeta['label'],
                    'sport_group_key' => $sportGroupKey,
                    'group_key' => $railMeta['group_key'],
                    'group_label' => $railMeta['group_label'],
                    'priority_score' => $priorityScore,
                    'priority_bucket' => $priorityBucket,
                    'rail_group_priority' => $railMeta['group_priority'],
                    'rail_item_priority' => $railMeta['item_priority'],
                    'rail_region_priority' => $railMeta['region_priority'],
                    'public_surface_tier' => $railMeta['public_surface_tier'],
                    'public_surface_rank' => $railMeta['public_surface_rank'],
                    'competition_asset' => $this->competitionAssetDescriptor($league, $railMeta['label']),
                    'count' => (int) ($league[$activePhaseCountKey] ?? count((array) ($league['matches'] ?? []))),
                ];
            })
            ->groupBy(fn (array $item) => $item['sport_group_key'] . '|' . $item['key'])
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'key' => $first['key'],
                    'label' => $first['label'],
                    'sport_group_key' => $first['sport_group_key'],
                    'group_key' => $first['group_key'] ?? 'other',
                    'group_label' => $first['group_label'] ?? $this->tournamentsFallbackLabel(),
                    'priority_score' => (int) $group->max('priority_score'),
                    'priority_bucket' => (string) ($first['priority_bucket'] ?? 'normal'),
                    'rail_group_priority' => (int) ($group->min('rail_group_priority') ?? PHP_INT_MAX),
                    'rail_item_priority' => (int) ($group->min('rail_item_priority') ?? PHP_INT_MAX),
                    'rail_region_priority' => (int) ($group->min('rail_region_priority') ?? PHP_INT_MAX),
                    'public_surface_tier' => $this->publicSurfaceTierFromRank((int) ($group->min('public_surface_rank') ?? $this->publicSurfaceTiers->rank('long_tail_surface'))),
                    'public_surface_rank' => (int) ($group->min('public_surface_rank') ?? $this->publicSurfaceTiers->rank('long_tail_surface')),
                    'competition_asset' => (array) ($first['competition_asset'] ?? $this->assetResolver->competition('', ['label' => (string) ($first['label'] ?? $this->tournamentFallbackLabel())])),
                    'count' => (int) $group->sum('count'),
                ];
            })
            ->values()
            ->sort(fn (array $left, array $right): int => $this->compareLeagueFilterItems($left, $right))
            ->values()
            ->all();
    }


    private function leagueRailMeta(array $league): array
    {
        $sportGroupKey = (string) ($league['sport_group_key'] ?? 'all');
        $title = (string) ($league['title'] ?? $league['label'] ?? $league['league_title'] ?? $this->tournamentFallbackLabel());

        if ($sportGroupKey !== 'soccer') {
            $groupMeta = $this->leagueFilterGroupMeta($title, $sportGroupKey);
            $surface = $this->publicSurfaceTiers->describe($league + [
                'sport_group_key' => $sportGroupKey,
                'competition_label' => $title,
            ]);

            return [
                'label' => $title !== '' ? $title : $this->tournamentFallbackLabel(),
                'group_key' => $groupMeta['key'],
                'group_label' => $groupMeta['label'],
                'group_priority' => PHP_INT_MAX,
                'item_priority' => PHP_INT_MAX,
                'region_priority' => PHP_INT_MAX,
                'public_surface_tier' => $surface['public_surface_tier'],
                'public_surface_rank' => $surface['public_surface_rank'],
            ];
        }

        $competitionKey = $this->footballRailCompetitionKey($league);
        $label = $this->footballRailLabel($title, $competitionKey);
        $regionKey = $this->footballRailRegionKey($league, $competitionKey, $title);
        $config = $this->footballRailConfig();
        $featuredConfig = (array) data_get($config, 'featured_competitions.' . $competitionKey, []);
        $featuredGroup = (array) data_get($config, 'featured_group', []);
        $surface = $this->publicSurfaceTiers->describe($league + [
            'sport_group_key' => $sportGroupKey,
            'competition_key' => $competitionKey,
            'competition_label' => $label,
        ]);

        if ($featuredConfig !== [] && $surface['public_surface_tier'] === 'premium_surface') {
            return [
                'label' => (string) data_get($featuredConfig, 'label', $label),
                'group_key' => (string) data_get($featuredGroup, 'key', 'soccer_featured'),
                'group_label' => (string) data_get($featuredGroup, 'label', (string) trans('cms_public.sportsbook.filters.sport_leagues', [
                    'sport' => $this->sportGroupLabel('soccer'),
                ])),
                'group_priority' => (int) data_get($featuredGroup, 'priority', 10),
                'item_priority' => (int) data_get($featuredConfig, 'priority', 500),
                'region_priority' => (int) data_get($config, 'regions.' . $regionKey . '.priority', 900),
                'public_surface_tier' => $surface['public_surface_tier'],
                'public_surface_rank' => $surface['public_surface_rank'],
            ];
        }

        $region = (array) data_get($config, 'regions.' . $regionKey, data_get($config, 'regions.other', []));

        return [
            'label' => $label,
            'group_key' => 'soccer_' . ($regionKey !== '' ? $regionKey : 'other'),
            'group_label' => (string) data_get($region, 'label', $this->sportGroupLabel('other')),
            'group_priority' => (int) data_get($region, 'priority', 900),
            'item_priority' => $this->footballRailItemPriority($competitionKey),
            'region_priority' => (int) data_get($region, 'priority', 900),
            'public_surface_tier' => $surface['public_surface_tier'],
            'public_surface_rank' => $surface['public_surface_rank'],
        ];
    }


    private function mapCatalogShellSelections(array $market, ?array $event = null): array
    {
        return collect($market['selections'] ?? [])
            ->map(fn (array $selection): array => $this->catalogShellSelectionItem($market, $selection, $event))
            ->values()
            ->all();
    }


    private function mapExtraMarkets(Collection $markets, ?array $event = null): array
    {
        return $markets
            ->reject(function (array $market) {
                return in_array($market['market_type'], ['match_result', 'total_goals', 'total_points'], true);
            })
            ->map(function (array $market) use ($event) {
                $marketName = $this->catalogMarketPresentationName($market, $event);
                $familyName = !empty($market['catalog_market_type'])
                    ? $this->catalogMarketPresentationName($market, $event, true)
                    : null;
                $selections = collect($market['selections'] ?? [])
                    ->filter(fn (array $selection) => $this->shouldRenderExtraMarketSelection($market, $selection))
                    ->map(function (array $selection) use ($market, $event, $marketName) {
                        if (!empty($market['is_catalog_shell']) || !empty($selection['is_catalog_shell'])) {
                            return $this->catalogShellSelectionItem($market, $selection, $event);
                        }

                        $outcomeName = $this->extraMarketOutcomeName($market, $selection, $event);
                        $interaction = $this->publicSelectionInteractionState($event, $market, $selection);
                        $selectable = $interaction['selectable'];
                        $disabled = !$selectable;
                        $selectionToken = $interaction['selection_token'];
                        $disabledReason = $this->publicSelectionBlockedReason($event, $market, $selection);

                        return [
                            'label' => $outcomeName !== '' ? $outcomeName : (string) trans('cms_public.sportsbook.js.selection_fallback'),
                            'raw_odd' => (string) ($selection['decimal_price'] ?? 0),
                            'odd' => $this->formatOdd((string) ($selection['decimal_price'] ?? 0)),
                            'quote_selection_id' => $selection['quote_selection_id'],
                            'selection_token' => $selectionToken,
                            'acceptance_mode' => $this->publicAcceptanceMode($selection, $selectionToken !== null),
                            'quote_version_id' => data_get($event, 'quote.quote_version_id'),
                            'event_id' => data_get($event, 'event_id'),
                            'market_type' => $market['market_type'],
                            'market_line' => $this->totalPresentationLine($market, $selection),
                            'market_specifier_key' => (string) ($market['specifier_key'] ?? 'default'),
                            'outcome_code' => (string) ($selection['outcome_code'] ?? ''),
                            'market_name' => $marketName,
                            'outcome_name' => $outcomeName !== '' ? $outcomeName : (string) trans('cms_public.sportsbook.js.selection_fallback'),
                            'event_name' => data_get($event, 'canonical_name'),
                            'selection_key' => $this->publicSelectionKey($event, $market, $selection),
                            'offer_status' => (string) ($selection['offer_status'] ?? 'open'),
                            'bettable_reason' => $selectable ? (string) ($selection['bettable_reason'] ?? '') : $disabledReason,
                            'selectable' => $selectable,
                            'acceptance_ready' => $selectable,
                            'disabled' => $disabled,
                            'visible' => true,
                            'contract_status' => $interaction['contract_status'],
                            'provider_state' => $interaction['provider_state'],
                            'selection_state_message' => $this->publicSelectionStateMessage($event, $market, $selection, $selectable),
                        ];
                    })
                    ->values()
                    ->all();

                if (empty($selections)) {
                    return null;
                }

                return [
                    'title' => $marketName,
                    'market_type' => (string) ($market['market_type'] ?? ''),
                    'market_family_key' => (string) ($market['market_family_key'] ?? $market['market_type'] ?? ''),
                    'market_family_chip' => $familyName ?? (string) ($market['market_family_chip'] ?? $market['display_name'] ?? ''),
                    'market_family_label' => $familyName ?? (string) ($market['market_family_label'] ?? $market['display_name'] ?? ''),
                    'market_family_order' => (int) ($market['market_family_order'] ?? $market['display_order'] ?? 999),
                    'is_catalog_shell' => (bool) ($market['is_catalog_shell'] ?? false),
                    'selections' => $selections,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }


    private function mapMatchResultSelections(?array $market, ?array $event = null): array
    {
        if (!$market) {
            return [];
        }

        if (!empty($market['is_catalog_shell'])) {
            return $this->mapCatalogShellSelections($market, $event);
        }

        $labelMap = [
            'home' => '1',
            'draw' => 'X',
            'away' => '2',
        ];

        $order = ['home', 'draw', 'away'];
        $selections = collect($market['selections'] ?? [])->keyBy('outcome_code');
        $marketName = $this->catalogMarketPresentationName($market, $event, false, (string) trans('cms_public.sportsbook.markets.match_result_lower'));

        return collect($order)
            ->filter(fn (string $code) => $selections->has($code))
            ->map(function (string $code) use ($selections, $labelMap, $market, $event, $marketName) {
                $selection = $selections[$code];
                $interaction = $this->publicSelectionInteractionState($event, $market, $selection);
                $selectable = $interaction['selectable'];
                $selectionToken = $interaction['selection_token'];
                $disabledReason = $this->publicSelectionBlockedReason($event, $market, $selection);

                return [
                    'label' => $labelMap[$code],
                    'raw_odd' => (string) $selection['decimal_price'],
                    'odd' => $this->formatOdd((string) $selection['decimal_price']),
                    'winx_edge_pct' => is_numeric($selection['winx_edge_pct'] ?? null) ? round((float) $selection['winx_edge_pct'], 1) : null,
                    'quote_selection_id' => $selection['quote_selection_id'],
                    'selection_token' => $selectionToken,
                    'acceptance_mode' => $this->publicAcceptanceMode($selection, $selectionToken !== null),
                    'quote_version_id' => data_get($event, 'quote.quote_version_id'),
                    'event_id' => data_get($event, 'event_id'),
                    'market_type' => $market['market_type'],
                    'market_specifier_key' => (string) ($market['specifier_key'] ?? 'default'),
                    'outcome_code' => $selection['outcome_code'],
                    'market_name' => $marketName,
                    'outcome_name' => $selection['display_name'],
                    'event_name' => data_get($event, 'canonical_name'),
                    'selection_key' => $this->publicSelectionKey($event, $market, $selection),
                    'offer_status' => (string) ($selection['offer_status'] ?? 'open'),
                    'bettable_reason' => $selectable ? (string) ($selection['bettable_reason'] ?? '') : $disabledReason,
                    'selectable' => $selectable,
                    'acceptance_ready' => $selectable,
                    'disabled' => !$selectable,
                    'visible' => true,
                    'contract_status' => $interaction['contract_status'],
                    'provider_state' => $interaction['provider_state'],
                    'selection_state_message' => $this->publicSelectionStateMessage($event, $market, $selection, $selectable),
                ];
            })
            ->values()
            ->all();
    }


    private function mapTotalsSelections(?array $market, ?array $event = null): array
    {
        if (!$market) {
            return [];
        }

        if (!empty($market['is_catalog_shell'])) {
            return $this->mapCatalogShellSelections($market, $event);
        }

        $line = $this->totalPresentationLine($market);
        $labelMap = [
            'under' => (string) trans('cms_public.sportsbook.markets.under') . ($line !== null ? ' ' . $line : ''),
            'over' => (string) trans('cms_public.sportsbook.markets.over') . ($line !== null ? ' ' . $line : ''),
        ];

        $order = ['under', 'over'];
        $selections = collect($market['selections'] ?? [])->keyBy('outcome_code');
        $marketName = $this->catalogMarketPresentationName($market, $event, false, (string) trans('cms_public.sportsbook.markets.total'));

        return collect($order)
            ->filter(fn (string $code) => $selections->has($code))
            ->map(function (string $code) use ($selections, $labelMap, $line, $market, $event, $marketName) {
                $selection = $selections[$code];
                $interaction = $this->publicSelectionInteractionState($event, $market, $selection);
                $selectable = $interaction['selectable'];
                $selectionToken = $interaction['selection_token'];
                $disabledReason = $this->publicSelectionBlockedReason($event, $market, $selection);

                return [
                    'label' => $labelMap[$code],
                    'raw_odd' => (string) $selection['decimal_price'],
                    'odd' => $this->formatOdd((string) $selection['decimal_price']),
                    'quote_selection_id' => $selection['quote_selection_id'],
                    'selection_token' => $selectionToken,
                    'acceptance_mode' => $this->publicAcceptanceMode($selection, $selectionToken !== null),
                    'quote_version_id' => data_get($event, 'quote.quote_version_id'),
                    'event_id' => data_get($event, 'event_id'),
                    'market_type' => $market['market_type'],
                    'market_line' => $line,
                    'market_specifier_key' => (string) ($market['specifier_key'] ?? 'default'),
                    'outcome_code' => $selection['outcome_code'],
                    'market_name' => $marketName,
                    'outcome_name' => $labelMap[$code],
                    'event_name' => data_get($event, 'canonical_name'),
                    'selection_key' => $this->publicSelectionKey($event, $market, $selection),
                    'offer_status' => (string) ($selection['offer_status'] ?? 'open'),
                    'bettable_reason' => $selectable ? (string) ($selection['bettable_reason'] ?? '') : $disabledReason,
                    'selectable' => $selectable,
                    'acceptance_ready' => $selectable,
                    'disabled' => !$selectable,
                    'visible' => true,
                    'contract_status' => $interaction['contract_status'],
                    'provider_state' => $interaction['provider_state'],
                    'selection_state_message' => $this->publicSelectionStateMessage($event, $market, $selection, $selectable),
                ];
            })
            ->values()
            ->all();
    }


    private function publicAcceptanceMode(array $selection, bool $hasSelectionToken): string
    {
        $mode = (string) ($selection['acceptance_mode'] ?? 'legacy_quote_selection');

        if ($mode === 'adapter_vps_quote_ref') {
            return $hasSelectionToken ? 'adapter_vps_selection_token' : 'adapter_vps_selection';
        }

        return $mode;
    }


    private function publicEventStartsAt(array $event): ?Carbon
    {
        $timestamp = (int) data_get($event, 'starts_at_timestamp', 0);
        if ($timestamp > 0) {
            return Carbon::createFromTimestamp($timestamp);
        }

        foreach (['starts_at', 'starts_at_iso'] as $key) {
            $value = trim((string) data_get($event, $key, ''));
            if ($value === '') {
                continue;
            }

            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }


    private function publicSelectionBlockedReason(?array $event, array $market, array $selection): string
    {
        if ($event !== null && !$this->isPublicEventBeforeAcceptanceCutoff($event)) {
            return 'event_cutoff_reached';
        }

        $explicitReason = trim((string) ($selection['bettable_reason'] ?? $market['bettable_reason'] ?? ''));
        if ($explicitReason === 'adapter_vps_missing_quote_ref') {
            return 'contract_refresh_required';
        }
        if ($explicitReason !== '') {
            return $explicitReason;
        }

        if (!(bool) data_get($event, 'quote.is_tradeable', false)) {
            return 'suspended';
        }

        if ((string) ($selection['offer_status'] ?? 'open') !== 'open'
            || (string) ($market['status'] ?? 'open') !== 'open') {
            return 'selection_not_open';
        }

        if ($this->isAdapterProviderSelection($event, $selection)
            && ((string) ($selection['acceptance_mode'] ?? '') !== 'adapter_vps_quote_ref'
                || trim((string) ($selection['quote_ref'] ?? '')) === '')) {
            return 'contract_refresh_required';
        }

        return (string) ($selection['bettable_reason'] ?? $market['bettable_reason'] ?? 'market_not_bettable');
    }


    private function publicSelectionContractStatus(?array $event, array $selection, ?string $token): string
    {
        if (!$this->isAdapterProviderSelection($event, $selection)) {
            return 'none';
        }
        if ($token !== null) {
            return 'ready';
        }
        if ((string) ($selection['acceptance_mode'] ?? '') === 'adapter_vps_event_scope') {
            return 'hydrating';
        }

        return 'missing';
    }


    private function publicSelectionInteractionState(?array $event, array $market, array $selection): array
    {
        $open = $this->isPublicSelectionOpenForAcceptance($event, $market, $selection);
        $token = $this->adapterSelectionToken($event ?? [], $market, $selection, $open);

        // Inventory eligibility does not imply a client has a usable contract.
        // Bulk reads can keep unhydrated events without making their odds clickable.
        return [
            'selectable' => $open && $this->isAdapterProviderSelection($event, $selection) && $token !== null,
            'selection_token' => $token,
            'contract_status' => $this->publicSelectionContractStatus($event, $selection, $token),
            'provider_state' => $this->publicSelectionProviderState($event, $market, $selection),
        ];
    }


    private function publicSelectionKey(?array $event, array $market, array $selection): string
    {
        return 'selection-' . sha1(implode('|', [
            (string) data_get($event, 'event_id', ''),
            (string) ($market['market_type'] ?? ''),
            (string) ($market['specifier_key'] ?? 'default'),
            (string) ($selection['outcome_code'] ?? ''),
        ]));
    }


    private function publicSelectionProviderState(?array $event, array $market, array $selection): string
    {
        $offerStatus = strtolower((string) ($selection['offer_status'] ?? ''));
        $marketStatus = strtolower((string) ($market['status'] ?? ''));
        if (in_array($offerStatus, ['suspended', 'paused'], true) || in_array($marketStatus, ['suspended', 'paused'], true)) {
            return 'suspended';
        }
        if (in_array($offerStatus, ['closed', 'settled', 'cancelled'], true)
            || in_array($marketStatus, ['closed', 'settled', 'cancelled'], true)) {
            return 'closed';
        }
        if ($offerStatus === 'open' && ($marketStatus === '' || $marketStatus === 'open')) {
            return 'open';
        }

        return 'unknown';
    }


    private function publicSelectionStateMessage(?array $event, array $market, array $selection, bool $selectable): string
    {
        if ($selectable) {
            return '';
        }

        return $this->selectionStateMessageForReason($this->publicSelectionBlockedReason($event, $market, $selection));
    }


    private function publicSurfaceTierFromRank(int $rank): string
    {
        return match ($rank) {
            $this->publicSurfaceTiers->rank('premium_surface') => 'premium_surface',
            $this->publicSurfaceTiers->rank('standard_surface') => 'standard_surface',
            $this->publicSurfaceTiers->rank('secondary_surface') => 'secondary_surface',
            default => 'long_tail_surface',
        };
    }


    private function scopeLeagueBlocksToSport(array $leagues, string $sportFilter): array
    {
        if ($sportFilter === 'all' || $sportFilter === 'upcoming' || $sportFilter === '') {
            return $leagues;
        }

        return collect($leagues)
            ->filter(fn (array $league): bool => (string) ($league['sport_group_key'] ?? '') === $sportFilter)
            ->values()
            ->all();
    }


    private function selectionStateMessageForReason(string $reason): string
    {
        return match ($reason) {
            'event_cutoff_reached' => (string) trans('cms_public.sportsbook.bet_feed.match_started'),
            'odds_not_available' => (string) trans('cms_public.sportsbook.market_unavailable'),
            'market_acceptance_rollout_disabled', 'market_settlement_rollout_disabled', 'market_not_bettable' => (string) trans('cms_public.sportsbook.js.unavailable_market'),
            'adapter_vps_acceptance_disabled' => (string) trans('cms_public.sportsbook.bet_feed.closed_to_betslip'),
            'market_outside_canary_scope' => (string) trans('cms_public.sportsbook.js.unavailable_market'),
            'market_settlement_not_supported' => (string) trans('cms_public.sportsbook.js.unavailable_market'),
            'market_acceptance_not_supported' => (string) trans('cms_public.sportsbook.js.unavailable_market'),
            'market_quote_not_supported' => (string) trans('cms_public.sportsbook.market_unavailable'),
            'selection_not_open', 'suspended' => (string) trans('cms_public.sportsbook.bet_feed.closed_to_betslip'),
            default => (string) trans('cms_public.sportsbook.js.unavailable_market'),
        };
    }

    private function shellAssetDescriptor(array $asset): array
    {
        return array_intersect_key($asset, array_flip([
            'canonical_key',
            'entity_type',
            'path',
            'placeholder',
            'placeholder_label',
            'icon_key',
            'color',
        ]));
    }


    private function shouldRenderExtraMarketSelection(array $market, array $selection): bool
    {
        if (!empty($market['is_catalog_shell']) || !empty($selection['is_catalog_shell'])) {
            return true;
        }

        if (!empty($selection['quote_selection_id']) || !empty($selection['quote_ref'])) {
            return true;
        }

        return (string) ($selection['acceptance_mode'] ?? '') === 'visible_only'
            && array_key_exists('decimal_price', $selection);
    }


    private function sportGroupKey(string $sportCode): string
    {
        return match (true) {
            str_starts_with($sportCode, 'soccer') => 'soccer',
            str_starts_with($sportCode, 'tennis') => 'tennis',
            str_starts_with($sportCode, 'basketball') => 'basketball',
            str_starts_with($sportCode, 'snooker') => 'snooker',
            str_contains($sportCode, 'table_tennis') => 'table_tennis',
            str_contains($sportCode, 'dota') => 'dota2',
            str_contains($sportCode, 'cs2') => 'cs2',
            str_starts_with($sportCode, 'volleyball') => 'volleyball',
            str_starts_with($sportCode, 'baseball') => 'baseball',
            str_starts_with($sportCode, 'icehockey') => 'icehockey',
            default => 'other',
        };
    }


    private function sportGroupLabel(string $sportGroupKey): string
    {
        return match ($sportGroupKey) {
            'soccer' => (string) trans('cms_public.sportsbook.sports.soccer'),
            'basketball' => (string) trans('cms_public.sportsbook.sports.basketball'),
            'tennis' => (string) trans('cms_public.sportsbook.sports.tennis'),
            'snooker' => (string) trans('cms_public.sportsbook.sports.snooker'),
            'table_tennis' => (string) trans('cms_public.sportsbook.sports.table_tennis'),
            'volleyball' => (string) trans('cms_public.sportsbook.sports.volleyball'),
            'baseball' => (string) trans('cms_public.sportsbook.sports.baseball'),
            'icehockey' => (string) trans('cms_public.sportsbook.sports.icehockey'),
            'dota2' => (string) trans('cms_public.sportsbook.sports.dota2'),
            'cs2' => (string) trans('cms_public.sportsbook.sports.cs2'),
            'valorant' => (string) trans('cms_public.sportsbook.sports.valorant'),
            'lol' => (string) trans('cms_public.sportsbook.sports.lol'),
            default => (string) trans('cms_public.sportsbook.sports.other'),
        };
    }


    private function sportLabel(string $sportCode): string
    {
        return match ($this->sportGroupKey($sportCode)) {
            'soccer' => (string) trans('cms_public.sportsbook.sports.soccer'),
            'tennis' => (string) trans('cms_public.sportsbook.sports.tennis'),
            'basketball' => (string) trans('cms_public.sportsbook.sports.basketball'),
            'snooker' => (string) trans('cms_public.sportsbook.sports.snooker'),
            'table_tennis' => (string) trans('cms_public.sportsbook.sports.table_tennis'),
            'dota2' => (string) trans('cms_public.sportsbook.sports.dota2'),
            'cs2' => (string) trans('cms_public.sportsbook.sports.cs2'),
            'volleyball' => (string) trans('cms_public.sportsbook.sports.volleyball'),
            'baseball' => (string) trans('cms_public.sportsbook.sports.baseball'),
            'icehockey' => (string) trans('cms_public.sportsbook.sports.icehockey'),
            default => (string) trans('cms_public.sportsbook.sports.other'),
        };
    }


    private function sportRailProductPriority(string $sportGroupKey): int
    {
        return self::SPORT_RAIL_PRODUCT_PRIORITY[$sportGroupKey] ?? PHP_INT_MAX;
    }


    private function sportsStrip(array $offerSummary, string $defaultSportFilter = 'soccer'): array
    {
        $sportItems = collect((array) ($offerSummary['sports'] ?? []))
            ->map(function (array $sport, string $sportGroupKey) use ($defaultSportFilter) {
                return [
                    'key' => $sportGroupKey,
                    'label' => (string) ($sport['label'] ?? $this->sportGroupLabel('other')),
                    'count' => (int) ($sport['pre_match_count'] ?? 0),
                    'pre_match_count' => (int) ($sport['pre_match_count'] ?? 0),
                    'in_game_count' => (int) ($sport['in_game_count'] ?? 0),
                    'sport_priority' => (int) ($sport['sport_priority'] ?? PHP_INT_MAX),
                    'product_priority' => $this->sportRailProductPriority($sportGroupKey),
                    'priority_bucket' => (string) ($sport['priority_bucket'] ?? 'normal'),
                    'nearest_starts_at_timestamp' => (int) ($sport['nearest_starts_at_timestamp'] ?? PHP_INT_MAX),
                    'active' => $sportGroupKey === $defaultSportFilter,
                    'asset_meta' => $this->assetResolver->sport(
                        $sportGroupKey,
                        (string) ($sport['label'] ?? $this->sportGroupLabel('other'))
                    ),
                ];
            })
            ->filter(function (array $sport): bool {
                return ($sport['key'] ?? '') !== 'upcoming'
                    && ((int) ($sport['pre_match_count'] ?? 0) + (int) ($sport['in_game_count'] ?? 0)) > 0;
            })
            ->sort(function (array $left, array $right): int {
                if (($left['product_priority'] ?? PHP_INT_MAX) !== ($right['product_priority'] ?? PHP_INT_MAX)) {
                    return ($left['product_priority'] ?? PHP_INT_MAX) <=> ($right['product_priority'] ?? PHP_INT_MAX);
                }

                if (($left['sport_priority'] ?? PHP_INT_MAX) !== ($right['sport_priority'] ?? PHP_INT_MAX)) {
                    return ($left['sport_priority'] ?? PHP_INT_MAX) <=> ($right['sport_priority'] ?? PHP_INT_MAX);
                }

                $leftNearest = (int) ($left['nearest_starts_at_timestamp'] ?? PHP_INT_MAX);
                $rightNearest = (int) ($right['nearest_starts_at_timestamp'] ?? PHP_INT_MAX);

                if ($leftNearest !== $rightNearest) {
                    return $leftNearest <=> $rightNearest;
                }

                if (($left['active'] ?? false) !== ($right['active'] ?? false)) {
                    return (int) (!!($right['active'] ?? false)) <=> (int) (!!($left['active'] ?? false));
                }

                return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
            })
            ->map(function (array $sport) {
                unset($sport['nearest_starts_at_timestamp']);
                unset($sport['product_priority']);

                return $sport;
            })
            ->values();

        if ($sportItems->isEmpty()) {
            return [];
        }

        if ($defaultSportFilter !== 'all' && !$sportItems->contains(fn (array $sport) => !empty($sport['active']))) {
            $firstKey = (string) data_get($sportItems->first(), 'key', 'soccer');
            $sportItems = $sportItems->map(function (array $sport) use ($firstKey) {
                $sport['active'] = $sport['key'] === $firstKey;

                return $sport;
            })->values();
        }

        return $sportItems->all();
    }


    private function teamAssetDescriptor(string $canonicalKey, string $label): array
    {
        $canonicalKey = trim($canonicalKey);
        $definition = $canonicalKey !== '' ? $this->catalog->team($canonicalKey) : null;

        return $this->assetResolver->team($canonicalKey, $definition ?: ['label' => $label]);
    }


    private function totalOutcomePresentationName(array $market, array $selection): ?string
    {
        if (!in_array((string) ($market['market_type'] ?? ''), ['total_goals', 'total_points', 'total_games'], true)) {
            return null;
        }

        $side = (string) ($selection['outcome_code'] ?? '');
        $line = $this->totalPresentationLine($market, $selection);
        if (!in_array($side, ['over', 'under'], true) || $line === null) {
            return null;
        }

        return (string) trans('cms_public.sportsbook.markets.' . $side) . ' ' . $line;
    }


    private function totalPresentationLine(array $market, array $selection = []): ?string
    {
        $line = $market['line'] ?? $selection['line'] ?? null;
        if (!is_numeric($line)) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $line, 6, '.', ''), '0'), '.');
    }


    private function tournamentFallbackLabel(): string
    {
        $label = trim((string) trans('cms_public.sportsbook.filters.tournament'));

        return $label !== '' ? $label : 'Tournament';
    }


    private function tournamentsFallbackLabel(): string
    {
        $label = trim((string) trans('cms_public.sportsbook.filters.tournaments'));

        return $label !== '' ? $label : 'Tournaments';
    }


    public function recentAcceptances(?CmsUser $authUser): array
    {
        if (!$authUser || !Schema::hasTable('betting_acceptances')) {
            return [];
        }

        $readModel = app(BettingAcceptanceSnapshotReadModel::class);

        return BettingAcceptance::query()
            ->with(['event', 'quoteSelection.outcome', 'settlement'])
            ->where('cms_user_id', $authUser->id)
            ->latest('id')
            ->limit(18)
            ->get()
            ->map(function (BettingAcceptance $acceptance) use ($readModel) {
                $category = $this->acceptanceCategory($acceptance);

                return [
                    'acceptance_ref' => $acceptance->acceptance_ref,
                    'short_acceptance_ref' => $this->shortPublicReference($acceptance->acceptance_ref),
                    'event_name' => $readModel->eventLabel($acceptance),
                    'market_name' => $readModel->marketLabel($acceptance),
                    'outcome_name' => $readModel->selectionLabel($acceptance),
                    'status' => $acceptance->status,
                    'decision' => $acceptance->decision,
                    'status_label' => $this->acceptanceStatusLabel($acceptance),
                    'category' => $category,
                    'stake_amount' => number_format((float) $acceptance->stake_amount, 2, ',', '.'),
                    'currency' => $acceptance->currency,
                    'accepted_price' => $this->formatOdd((string) $acceptance->accepted_price),
                    'requested_at_label' => optional($acceptance->requested_at)->format('d.m.Y H:i') ?: '-',
                ];
            })
            ->all();
    }

    private function shortPublicReference(?string $reference): string
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return '-';
        }

        $normalized = str_replace('-', '', $reference);
        if (strlen($normalized) >= 8) {
            return strtoupper(substr($normalized, 0, 4) . '...' . substr($normalized, -4));
        }

        return strtoupper(Str::limit($reference, 12, ''));
    }

    private function acceptanceCategory(BettingAcceptance $acceptance): string
    {
        $settlement = $acceptance->settlement;

        if ($settlement && !in_array((string) $settlement->status, ['pending', 'review'], true)) {
            return 'resulted';
        }

        if (in_array((string) $acceptance->status, ['rejected', 'cancelled'], true)) {
            return 'closed';
        }

        return 'open';
    }

    private function acceptanceStatusLabel(BettingAcceptance $acceptance): string
    {
        $settlement = $acceptance->settlement;

        if ($settlement && !empty($settlement->result_code) && !in_array((string) $settlement->result_code, ['pending', 'review'], true)) {
            $resultCode = (string) $settlement->result_code;
            $knownResults = ['won', 'lost', 'void', 'cancelled', 'postponed', 'refunded', 'refund'];
            return (string) trans('cms_public.sportsbook.history.status_result', [
                'result' => in_array($resultCode, $knownResults, true)
                    ? trans('cms_public.sportsbook.history.result_' . $resultCode)
                    : trans('cms_public.sportsbook.history.status_unavailable'),
            ]);
        }

        return match ((string) $acceptance->status) {
            'pending' => (string) trans('cms_public.sportsbook.history.status_pending'),
            'accepted' => (string) trans('cms_public.sportsbook.history.status_accepted'),
            'held' => (string) trans('cms_public.sportsbook.history.status_held'),
            'suspended' => (string) trans('cms_public.sportsbook.history.status_suspended'),
            'rejected' => (string) trans('cms_public.sportsbook.history.status_rejected'),
            'cancelled' => (string) trans('cms_public.sportsbook.history.status_cancelled'),
            'settled' => (string) trans('cms_public.sportsbook.history.status_settled'),
            default => (string) trans('cms_public.sportsbook.history.status_unavailable'),
        };
    }


    private function addCatalogLeagueCount(array &$counts, string $key, int $preMatch, int $inGame, string $leagueFilterKey): void
    {
        if (!isset($counts[$key])) {
            $counts[$key] = [
                'event_count' => 0,
                'pre_match_count' => 0,
                'in_game_count' => 0,
                'league_filter_key' => $leagueFilterKey,
            ];
        }

        $counts[$key]['pre_match_count'] += $preMatch;
        $counts[$key]['in_game_count'] += $inGame;
        $counts[$key]['event_count'] = $counts[$key]['pre_match_count'] + $counts[$key]['in_game_count'];
        if ((string) ($counts[$key]['league_filter_key'] ?? '') === '' && $leagueFilterKey !== '') {
            $counts[$key]['league_filter_key'] = $leagueFilterKey;
        }
    }


    private function catalogActiveCountLabel(int $eventCount): string
    {
        if ($eventCount <= 0) {
            return (string) trans('cms_public.sportsbook.common.catalog_has_no_active_matches');
        }

        $locale = strtolower((string) app()->getLocale());

        return match (true) {
            str_starts_with($locale, 'en') => trim((string) $eventCount . ' active ' . (string) trans('cms_public.sportsbook.common.matches')),
            str_starts_with($locale, 'es') => trim((string) $eventCount . ' ' . (string) trans('cms_public.sportsbook.common.matches') . ' activos'),
            default => trim((string) $eventCount . ' aktif ' . (string) trans('cms_public.sportsbook.common.matches')),
        };
    }


    private function catalogAvailabilityLabel(int $eventCount): string
    {
        return $eventCount > 0
            ? (string) trans('cms_public.sportsbook.common.active_match_exists')
            : (string) trans('cms_public.sportsbook.common.catalog_has_no_active_matches');
    }


    private function catalogCountKeysForCompetition(array $competition, string $canonicalKey): array
    {
        $sportGroupKey = (string) ($competition['sport_group_key'] ?? '');
        $countryKey = (string) ($competition['country_key'] ?? '');
        $legacyKey = (string) ($competition['legacy_key'] ?? '');
        $competitionKey = (string) ($competition['competition_key'] ?? '');
        $identityKeys = collect(array_merge([
            (string) ($competition['label'] ?? ''),
            (string) ($competition['short_label'] ?? ''),
        ], (array) ($competition['aliases'] ?? [])))
            ->map(fn ($label): string => $this->catalog->normalizeKey((string) $label))
            ->filter()
            ->unique()
            ->values();
        $countryKeys = collect([
            $countryKey,
            $this->catalog->countryLabel($countryKey),
        ])
            ->map(fn ($country): string => $this->catalog->normalizeKey((string) $country))
            ->filter()
            ->unique()
            ->values();
        $countryQualifiedIdentityKeys = $identityKeys
            ->flatMap(fn (string $identityKey): Collection => $countryKeys->flatMap(fn (string $normalizedCountryKey): array => [
                $normalizedCountryKey . '_' . $identityKey,
                $identityKey . '_' . $normalizedCountryKey,
            ]))
            ->unique()
            ->values();
        $labelKeys = $identityKeys
            ->merge($countryQualifiedIdentityKeys)
            ->unique()
            ->values();

        return collect([
            $canonicalKey,
            $legacyKey,
            $sportGroupKey !== '' && $legacyKey !== '' ? $sportGroupKey . '--' . $legacyKey : '',
            $competitionKey,
            $sportGroupKey !== '' && $competitionKey !== '' ? $sportGroupKey . '--' . $competitionKey : '',
        ])
            ->merge($labelKeys)
            ->merge($labelKeys->map(fn (string $key): string => $sportGroupKey !== '' ? $sportGroupKey . '--' . $key : ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }


    private function catalogInventoryDefinitions(array $summary, array &$countries, array &$regions): array
    {
        $definitions = $this->catalog->competitions();
        $counts = [];
        // Assign each inventory league once. Aliases are matching candidates,
        // never independent counters; the emitted filter is the inventory key.
        foreach ((array) ($summary['leagues'] ?? []) as $league) {
            $filter = (string) ($league['league_filter_key'] ?? '');
            $sport = (string) ($league['sport_group_key'] ?? 'other');
            if ($filter === '' || (int) ($league['pre_match_count'] ?? 0) + (int) ($league['in_game_count'] ?? 0) === 0) {
                continue;
            }
            $canonical = (string) ($league['canonical_competition_key'] ?? '');
            $match = isset($definitions[$canonical]) && ($definitions[$canonical]['sport_group_key'] ?? '') === $sport ? $canonical : null;
            if ($match === null) {
                $aliases = array_keys($this->catalogLeagueCounts(['leagues' => [$league]]));
                foreach ($definitions as $key => $definition) {
                    if (($definition['sport_group_key'] ?? '') === $sport
                        && ($definition['visibility'] ?? 'visible') === 'visible'
                        && !empty($definition['shell_enabled'])
                        && array_intersect($aliases, $this->catalogCountKeysForCompetition($definition, $key)) !== []) {
                        $match = $key;
                        break;
                    }
                }
            }
            if ($match !== null && (($definitions[$match]['visibility'] ?? 'visible') !== 'visible' || empty($definitions[$match]['shell_enabled']))) {
                continue;
            }
            if ($match === null) {
                $match = 'inventory:' . $sport . ':' . hash('sha256', $filter);
                $country = (string) ($league['country_key'] ?? '');
                if ($country === '' || !isset($countries[$country])) {
                    $country = 'inventory_other';
                }
                $definitions[$match] = [
                    'competition_key' => $filter,
                    'sport_group_key' => $sport,
                    'country_key' => $country,
                    'region_key' => $countries[$country]['region_key'] ?? 'inventory_other',
                    'label' => (string) ($league['label'] ?? $league['league_title'] ?? $this->tournamentFallbackLabel()),
                    'visibility' => 'visible', 'shell_enabled' => true,
                    'source_status' => 'inventory_fallback', 'identity_state' => 'fallback',
                ];
            } elseif (isset($counts[$match]) && $counts[$match]['league_filter_key'] !== $filter) {
                // Different provider filter keys must remain separately navigable.
                $variant = $match . ':inventory:' . hash('sha256', $filter);
                $definitions[$variant] = $definitions[$match];
                $match = $variant;
            }
            $definition = $definitions[$match];
            $country = (string) ($definition['country_key'] ?? 'inventory_other');
            if (!isset($countries[$country])) {
                $countries[$country] = [
                    'region_key' => 'inventory_other', 'label' => trans('cms_public.sportsbook.filters.other_tournaments'),
                    'visibility' => 'visible', 'source_status' => 'inventory_fallback',
                ];
            }
            $region = (string) ($countries[$country]['region_key'] ?? 'inventory_other');
            if (!isset($regions[$region])) {
                $regions[$region] = ['label' => $this->sportGroupLabel('other'), 'visibility' => 'visible', 'source_status' => 'inventory_fallback'];
            }
            $this->addCatalogLeagueCount($counts, $match, (int) ($league['pre_match_count'] ?? 0), (int) ($league['in_game_count'] ?? 0), $filter);
        }

        return [$definitions, $counts];
    }


    private function catalogLeagueCounts(array $offerSummary): array
    {
        $counts = [];

        foreach ((array) ($offerSummary['leagues'] ?? []) as $league) {
            if (!is_array($league)) {
                continue;
            }

            $preMatch = (int) ($league['pre_match_count'] ?? 0);
            $inGame = (int) ($league['in_game_count'] ?? 0);
            $leagueFilterKey = (string) ($league['league_filter_key'] ?? '');
            $sportGroupKey = (string) ($league['sport_group_key'] ?? '');
            $normalizedLeagueFilterKey = $leagueFilterKey !== '' && str_contains($leagueFilterKey, '--')
                ? (string) Str::after($leagueFilterKey, '--')
                : $leagueFilterKey;
            $normalizedTitle = $this->catalog->normalizeKey((string) ($league['league_title'] ?? $league['label'] ?? ''));

            $keys = collect([
                (string) ($league['canonical_competition_key'] ?? ''),
                $leagueFilterKey,
                $normalizedLeagueFilterKey,
                (string) ($league['competition_group_key'] ?? ''),
                $normalizedTitle,
                $sportGroupKey !== '' && $normalizedTitle !== '' ? $sportGroupKey . '--' . $normalizedTitle : '',
            ])
                ->filter()
                ->unique()
                ->values();

            foreach ($keys as $key) {
                $this->addCatalogLeagueCount($counts, (string) $key, $preMatch, $inGame, $leagueFilterKey);
            }
        }

        return $counts;
    }


    public function catalogTreePayload(array $offerSummary, string $sportFilter = '', bool $countsAreProviderBacked = true): array
    {
        $regions = $this->catalog->regions();
        $countries = $this->catalog->countries();
        [$definitions, $leagueCounts] = $this->catalogInventoryDefinitions($offerSummary, $countries, $regions);
        $competitions = collect($definitions)
            ->filter(function (array $competition) use ($sportFilter): bool {
                if ((string) ($competition['visibility'] ?? 'visible') !== 'visible') {
                    return false;
                }

                if (empty($competition['shell_enabled'])) {
                    return false;
                }

                return $sportFilter === '' || (string) ($competition['sport_group_key'] ?? '') === $sportFilter;
            })
            ->map(function (array $competition, string $canonicalKey) use ($leagueCounts): array {
                $counts = $leagueCounts[$canonicalKey] ?? [];
                $eventCount = (int) ($counts['event_count'] ?? 0);

                return [
                    'canonical_competition_key' => $canonicalKey,
                    'competition_key' => (string) ($competition['competition_key'] ?? ''),
                    'sport_group_key' => (string) ($competition['sport_group_key'] ?? 'other'),
                    'region_key' => (string) ($competition['region_key'] ?? 'other'),
                    'country_key' => (string) ($competition['country_key'] ?? 'unknown'),
                    'label' => (string) ($competition['label'] ?? $this->tournamentFallbackLabel()),
                    'short_label' => (string) ($competition['short_label'] ?? $competition['label'] ?? $this->tournamentFallbackLabel()),
                    'tier' => (string) ($competition['tier'] ?? 'standard'),
                    'public_surface' => (string) ($competition['public_surface'] ?? 'standard_surface'),
                    'priority' => (int) ($competition['priority'] ?? 500),
                    'source_status' => (string) ($competition['source_status'] ?? 'needs_review'),
                    'identity_state' => (string) ($competition['identity_state'] ?? 'canonical'),
                    'asset_meta' => (array) ($competition['asset_meta'] ?? []),
                    'event_count' => $eventCount,
                    'pre_match_count' => (int) ($counts['pre_match_count'] ?? 0),
                    'in_game_count' => (int) ($counts['in_game_count'] ?? 0),
                    'league_filter_key' => (string) ($counts['league_filter_key'] ?? ''),
                    'has_live_events' => $eventCount > 0,
                    'count_label' => $this->catalogActiveCountLabel($eventCount),
                    'availability_label' => $this->catalogAvailabilityLabel($eventCount),
                    'empty_label' => (string) trans('cms_public.sportsbook.common.no_active_match'),
                ];
            })
            ->sortBy([
                ['event_count', 'desc'],
                ['priority', 'asc'],
                ['label', 'asc'],
            ])
            ->values();

        $countriesPayload = collect($countries)
            ->filter(fn (array $country): bool => (string) ($country['visibility'] ?? 'visible') === 'visible')
            ->map(function (array $country, string $countryKey) use ($competitions): array {
                $countryCompetitions = $competitions
                    ->where('country_key', $countryKey)
                    ->sortBy([
                        ['event_count', 'desc'],
                        ['priority', 'asc'],
                        ['label', 'asc'],
                    ])
                    ->values();

                $eventCount = (int) $countryCompetitions->sum('event_count');

                return [
                    'country_key' => $countryKey,
                    'region_key' => (string) ($country['region_key'] ?? 'other'),
                    'iso2' => (string) ($country['iso2'] ?? ''),
                    'label' => (string) ($country['label'] ?? ucfirst(str_replace('_', ' ', $countryKey))),
                    'priority' => (int) ($country['priority'] ?? 500),
                    'source_status' => (string) ($country['source_status'] ?? 'needs_review'),
                    'asset_meta' => (array) ($country['asset_meta'] ?? []),
                    'event_count' => $eventCount,
                    'competition_count' => $countryCompetitions->count(),
                    'count_label' => $this->catalogActiveCountLabel($eventCount),
                    'availability_label' => $this->catalogAvailabilityLabel($eventCount),
                    'competitions' => $countryCompetitions->all(),
                ];
            })
            ->filter(fn (array $country): bool => (int) ($country['competition_count'] ?? 0) > 0)
            ->values();

        $regionsPayload = collect($regions)
            ->filter(fn (array $region): bool => (string) ($region['visibility'] ?? 'visible') === 'visible')
            ->map(function (array $region, string $regionKey) use ($countriesPayload): array {
                $regionCountries = $countriesPayload
                    ->where('region_key', $regionKey)
                    ->sortBy([
                        ['event_count', 'desc'],
                        ['priority', 'asc'],
                        ['label', 'asc'],
                    ])
                    ->values();

                $eventCount = (int) $regionCountries->sum('event_count');

                return [
                    'region_key' => $regionKey,
                    'label' => (string) ($region['label'] ?? ucfirst(str_replace('_', ' ', $regionKey))),
                    'priority' => (int) ($region['priority'] ?? 500),
                    'source_status' => (string) ($region['source_status'] ?? 'needs_review'),
                    'asset_meta' => (array) ($region['asset_meta'] ?? []),
                    'event_count' => $eventCount,
                    'country_count' => $regionCountries->count(),
                    'competition_count' => (int) $regionCountries->sum('competition_count'),
                    'count_label' => $this->catalogActiveCountLabel($eventCount),
                    'availability_label' => $this->catalogAvailabilityLabel($eventCount),
                    'countries' => $regionCountries->all(),
                ];
            })
            ->filter(fn (array $region): bool => (int) ($region['competition_count'] ?? 0) > 0)
            ->sortBy([
                ['event_count', 'desc'],
                ['priority', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'sport_group_key' => $sportFilter,
            'sport_asset' => $this->assetResolver->sport(
                $sportFilter,
                $this->sportGroupLabel($sportFilter !== '' ? $sportFilter : 'other')
            ),
            'counts_are_provider_backed' => $countsAreProviderBacked,
            'empty_label' => (string) trans('cms_public.sportsbook.common.no_active_match'),
            'regions' => $regionsPayload,
        ];
    }


    public function liveOfferCountsPayload(array $offerSummary, Collection $shellEvents): array
    {
        $phaseCounts = (array) ($offerSummary['tab_counts'] ?? []);

        return [
            'scope' => 'page_and_inventory_counts',
            'semantics' => [
                'visible_event_count' => 'current_page_events',
                'visible_row_count' => 'current_page_rows',
                'phase_counts' => 'full_inventory_by_phase',
                'sport_group_counts' => 'full_inventory_by_sport_group',
                'league_counts' => 'full_inventory_by_league',
            ],
            'visible_event_count' => (int) $shellEvents->sum(fn (array $event): int => $this->shellEventCount($event)),
            'visible_row_count' => $shellEvents->count(),
            'phase_counts' => [
                'pre-match' => (int) ($phaseCounts['pre-match'] ?? 0),
                'in-game' => (int) ($phaseCounts['in-game'] ?? 0),
            ],
            'sport_group_counts' => collect((array) ($offerSummary['sports'] ?? []))
                ->mapWithKeys(function (array $sport, string $sportGroupKey): array {
                    $preMatchCount = (int) ($sport['pre_match_count'] ?? 0);
                    $inGameCount = (int) ($sport['in_game_count'] ?? 0);

                    return [
                        $sportGroupKey => [
                            'label' => (string) ($sport['label'] ?? $this->sportGroupLabel('other')),
                            'pre_match_count' => $preMatchCount,
                            'in_game_count' => $inGameCount,
                            'visible_count' => $preMatchCount + $inGameCount,
                        ],
                    ];
                })
                ->all(),
            'league_counts' => collect((array) ($offerSummary['leagues'] ?? []))
                ->mapWithKeys(function (array $league, string $leagueKey): array {
                    $preMatchCount = (int) ($league['pre_match_count'] ?? 0);
                    $inGameCount = (int) ($league['in_game_count'] ?? 0);

                    return [
                        $leagueKey => [
                            'label' => (string) ($league['label'] ?? $this->tournamentFallbackLabel()),
                            'sport_group_key' => (string) ($league['sport_group_key'] ?? 'other'),
                            'pre_match_count' => $preMatchCount,
                            'in_game_count' => $inGameCount,
                            'visible_count' => $preMatchCount + $inGameCount,
                        ],
                    ];
                })
                ->all(),
        ];
    }


    private function shellEventCount(array $event): int
    {
        return max(1, (int) ($event['event_count'] ?? 1));
    }


}
