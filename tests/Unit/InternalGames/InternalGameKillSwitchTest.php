<?php

declare(strict_types=1);

namespace Tests\Unit\InternalGames;

use App\Models\CmsGame;
use App\Models\Provider;
use App\Services\InternalGames\InternalGameCatalog;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class InternalGameKillSwitchTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $app = new Container();
        $app->instance('config', new Repository([
            'internal_games' => [
                'enabled' => true,
                'games' => ['dice' => ['slug' => 'dice']],
            ],
        ]));
        Container::setInstance($app);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function game(?bool $killSwitch, string $status = 'active', bool $maintenance = false): CmsGame
    {
        $config = $killSwitch === null ? [] : ['kill_switch' => $killSwitch];
        // Offline fixture: set raw attributes to avoid model guard DB metadata.
        $provider = new Provider();
        $provider->setRawAttributes([
            'slug' => 'internal-games',
            'kind' => 'internal',
            'status' => 'active',
            'show_left_panel' => 1,
            'is_maintenance' => 0,
            'config' => json_encode($config, JSON_THROW_ON_ERROR),
        ], true);
        $game = new CmsGame();
        $game->setRawAttributes([
            'slug' => 'dice',
            'reference_id' => 'internal:dice',
            'status' => $status,
            'is_restricted' => 0,
            'is_maintenance' => $maintenance ? 1 : 0,
        ], true);
        $game->setRelation('provider', $provider);
        return $game;
    }

    public function test_provider_kill_switch_blocks_new_internal_game_admission(): void
    {
        $catalog = new InternalGameCatalog();
        self::assertFalse($catalog->allowsNewAdmission($this->game(true)));
        self::assertTrue($catalog->isPlayable($this->game(true)), 'Existing sessions preserve readback and settlement checks.');
    }

    public function test_false_or_missing_provider_kill_switch_preserves_normal_playability(): void
    {
        $catalog = new InternalGameCatalog();
        self::assertTrue($catalog->allowsNewAdmission($this->game(false)));
        self::assertTrue($catalog->allowsNewAdmission($this->game(null)));
    }

    public function test_existing_maintenance_and_catalog_visibility_guards_remain_effective(): void
    {
        $catalog = new InternalGameCatalog();
        self::assertFalse($catalog->allowsNewAdmission($this->game(false, 'inactive')));
        self::assertFalse($catalog->allowsNewAdmission($this->game(false, 'active', true)));
    }
}
